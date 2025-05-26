<?php
require_once __DIR__ . '/Connection.php';
$connection = new Connection();

// Mensagens de status
$error = '';
$success = '';

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link'])) 
{
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $color_id = filter_input(INPUT_POST, 'color_id', FILTER_VALIDATE_INT);

    if (!$user_id || !$color_id) 
    {
        $error = "Selecione usuário e cor válidos para vincular.";
    } 
    else 
    {
        try {
            // Verifica se já existe a vinculação
            $stmtCheck = $connection->prepareAndExecute(
                "SELECT COUNT(*) FROM user_colors WHERE user_id = :user_id AND color_id = :color_id",
                [':user_id' => $user_id, ':color_id' => $color_id]
            );
            
            if ($stmtCheck->fetchColumn() > 0) 
            {
                $error = "Usuário já está vinculado a essa cor.";
            } else 
            {
                $connection->prepareAndExecute(
                    "INSERT INTO user_colors (user_id, color_id) VALUES (:user_id, :color_id)",
                    [':user_id' => $user_id, ':color_id' => $color_id]
                );
                $success = "Vinculação realizada com sucesso!";
            }
        } catch (PDOException $e) {
            $error = "Erro ao vincular: " . $e->getMessage();
        }
    }
}

// Processa desvinculação via GET
if (isset($_GET['action'], $_GET['user_id'], $_GET['color_id']) && $_GET['action'] === 'unlink') 
{
    try {
        $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        $color_id = filter_input(INPUT_GET, 'color_id', FILTER_VALIDATE_INT);
        
        if ($user_id && $color_id) 
        {
            $connection->prepareAndExecute(
                "DELETE FROM user_colors WHERE user_id = :user_id AND color_id = :color_id",
                [':user_id' => $user_id, ':color_id' => $color_id]
            );
            $success = "Desvinculação realizada com sucesso!";
            header("Location: index.php?view=usercolors");
            exit;
        } else {
            $error = "IDs inválidos para desvinculação.";
        }
    } catch (PDOException $e) {
        $error = "Erro ao desvincular: " . $e->getMessage();
    }
}

// Busca dados para os selects e tabela
try {
    $users = $connection->prepareAndExecute("SELECT id, name FROM users ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
    $colors = $connection->prepareAndExecute("SELECT id, name FROM colors ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
    
    $usercolors = $connection->prepareAndExecute("
        SELECT 
            uc.user_id, 
            u.name AS user_name,
            uc.color_id,
            c.name AS color_name
        FROM user_colors uc
        JOIN users u ON u.id = uc.user_id
        JOIN colors c ON c.id = uc.color_id
        ORDER BY u.name, c.name
    ")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $error = "Erro ao carregar dados: " . $e->getMessage();
    $users = $colors = $usercolors = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Usuários e Cores</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 0.35rem 0.35rem 0 0 !important;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--secondary-color);
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .btn-info {
            background-color: var(--info-color);
            border-color: var(--info-color);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .badge-user {
            background-color: #e83e8c;
        }
        
        .badge-color {
            background-color: #20c997;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--secondary-color);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dddfeb;
        }
        
        .link-back {
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .link-back:hover {
            color: #2a4b9b;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <!-- Cabeçalho com botão de retorno -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="index.php" class="link-back">
                    <i class="bi bi-arrow-left me-2"></i>Voltar ao Menu
                </a>
                <h1 class="h2 mt-2">
                    <i class="bi bi-link-45deg me-2"></i>Gerenciamento de Usuários e Cores
                </h1>
            </div>
            <span class="badge bg-primary rounded-pill">
                <?= count($usercolors) ?> <?= count($usercolors) === 1 ? 'vinculação' : 'vinculaçōes' ?>
            </span>
        </div>

        <!-- Mensagens -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Formulário de Vinculação -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="bi bi-plus-circle me-1"></i>Nova Vinculação
                </h2>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <div class="col-md-5">
                        <label for="user_id" class="form-label">
                            <i class="bi bi-person-fill me-1"></i>Usuário
                        </label>
                        <select name="user_id" id="user_id" class="form-select" required>
                            <option value="">Selecione um usuário...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= htmlspecialchars($user->id, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-5">
                        <label for="color_id" class="form-label">
                            <i class="bi bi-palette-fill me-1"></i>Cor
                        </label>
                        <select name="color_id" id="color_id" class="form-select" required>
                            <option value="">Selecione uma cor...</option>
                            <?php foreach ($colors as $color): ?>
                                <option value="<?= htmlspecialchars($color->id, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($color->name, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="link" class="btn btn-primary w-100">
                            <i class="bi bi-link me-1"></i>Vincular
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Vinculações -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="bi bi-list-ul me-1"></i>Vinculações Existentes
                </h2>
            </div>
            <div class="card-body p-0">
                <?php if (empty($usercolors)): ?>
                    <div class="empty-state">
                        <i class="bi bi-unlink"></i>
                        <h3 class="h5">Nenhuma vinculação encontrada</h3>
                        <p class="mb-4">Comece vinculando usuários a cores usando o formulário acima</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Usuário</th>
                                    <th>Cor</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usercolors as $uc): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-user me-2">#<?= htmlspecialchars($uc->user_id, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?= htmlspecialchars($uc->user_name, ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-color me-2">#<?= htmlspecialchars($uc->color_id, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?= htmlspecialchars($uc->color_name, ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="confirmUnlink(<?= htmlspecialchars($uc->user_id, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($uc->color_id, ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-unlink me-1"></i>Desvincular
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function confirmUnlink(userId, colorId) {
            if (confirm('Tem certeza que deseja desvincular esta cor do usuário?')) {
                window.location.href = `?view=usercolors&action=unlink&user_id=${userId}&color_id=${colorId}`;
            }
        }
        
        // Validação.....
        document.addEventListener('DOMContentLoaded', function() {
            const userSelect = document.getElementById('user_id');
            const colorSelect = document.getElementById('color_id');
            const linkButton = document.querySelector('[name="link"]');
            
            function validateForm() {
                if (userSelect.value && colorSelect.value) {
                    linkButton.disabled = false;
                } else {
                    linkButton.disabled = true;
                }
            }
            
            userSelect.addEventListener('change', validateForm);
            colorSelect.addEventListener('change', validateForm);
            
            // Validar inicialmente
            validateForm();
        });
    </script>
</body>
</html>