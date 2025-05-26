<?php
require_once __DIR__ . '/Connection.php';
$connection = new Connection();
$pdo = $connection->getConnection();

// Mensagens
$error = '';
$success = '';
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$colorToEdit = null;

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $postId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    // Validações
    $errors = [];
    
    if (empty($name)) 
    {
        $errors['name'] = "Nome da cor é obrigatório.";
    } 
    elseif (strlen($name) < 2) 
    {
        $errors['name'] = "Nome deve ter pelo menos 2 caracteres.";
    } 
    elseif (strlen($name) > 50) 
    {
        $errors['name'] = "Nome deve ter no máximo 50 caracteres.";
    }

    if (empty($errors)) 
    {
        try {
            if (isset($_POST['create'])) 
            {
                // Verificar se cor já existe
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM colors WHERE name = :name");
                $stmtCheck->execute([':name' => $name]);
                
                if ($stmtCheck->fetchColumn() > 0) {
                    $errors['name'] = "Esta cor já está cadastrada.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO colors (name) VALUES (:name)");
                    $stmt->execute([':name' => $name]);
                    $_SESSION['success_message'] = "Cor criada com sucesso!";
                    header("Location: index.php?view=colors");
                    exit;
                }
            } 
            elseif (isset($_POST['edit']) && $postId) 
            {
                // Verificar se cor já existe mas neh
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM colors WHERE name = :name AND id != :id");
                $stmtCheck->execute([':name' => $name, ':id' => $postId]);
                
                if ($stmtCheck->fetchColumn() > 0) 
                {
                    $errors['name'] = "Esta cor já está cadastrada para outro registro.";
                } 
                else 
                {
                    $stmt = $pdo->prepare("UPDATE colors SET name = :name WHERE id = :id");
                    $stmt->execute([':name' => $name, ':id' => $postId]);
                    $_SESSION['success_message'] = "Cor atualizada com sucesso!";
                    header("Location: index.php?view=colors");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = "Erro ao salvar: " . $e->getMessage();
        }
    } 
    else 
    {
        $error = "Corrija os erros abaixo.";
    }
}

// Processar DELETE
if ($action === 'delete' && $id) 
{
    try {
        $stmt = $pdo->prepare("DELETE FROM colors WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success_message'] = "Cor excluída com sucesso!";
        header("Location: index.php?view=colors");
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao excluir: " . $e->getMessage();
    }
}

// Carregar para edição
if ($action === 'edit' && $id) 
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM colors WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $colorToEdit = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$colorToEdit) 
        {
            $error = "Cor não encontrada.";
        }
    } catch (PDOException $e) {
        $error = "Erro ao carregar: " . $e->getMessage();
    }
}

// Listar cores
try {
    $colors = $pdo->query("SELECT * FROM colors ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $error = "Erro ao listar cores: " . $e->getMessage();
    $colors = [];
}

// imprime msg da seção
if (isset($_SESSION['success_message'])) 
{
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Cores</title>
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
            --warning-color: #f6c23e;
        }
        
        body {
            background-color: #f8f9fc;
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
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--secondary-color);
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
        }
        
        .badge-color {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            vertical-align: middle;
            margin-right: 8px;
            border: 1px solid #dee2e6;
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
                    <i class="bi bi-palette me-2"></i>Gerenciamento de Cores
                </h1>
            </div>
            <span class="badge bg-primary rounded-pill">
                <?= count($colors) ?> <?= count($colors) === 1 ? 'cor' : 'cores' ?>
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

        <!-- Formulário -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">
                    <i class="bi bi-<?= $colorToEdit ? 'pencil' : 'plus' ?>-circle me-1"></i>
                    <?= $colorToEdit ? 'Editar Cor' : 'Nova Cor' ?>
                </h2>
                <?php if ($colorToEdit): ?>
                    <a href="index.php?view=colors" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="post" id="colorForm">
                    <input type="hidden" name="id" value="<?= $colorToEdit->id ?? '' ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="bi bi-tag-fill me-1"></i>Nome da Cor
                        </label>
                        <input type="text" name="name" id="name" 
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                               value="<?= isset($colorToEdit->name) ? htmlspecialchars($colorToEdit->name, ENT_QUOTES, 'UTF-8') : ($_POST['name'] ?? '') ?>" 
                               required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback">
                                <i class="bi bi-exclamation-circle me-1"></i>
                                <?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                        <small class="text-muted">Mínimo 2 caracteres, máximo 50 caracteres</small>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" name="<?= $colorToEdit ? 'edit' : 'create' ?>" 
                                class="btn btn-success me-2">
                            <i class="bi bi-check-lg me-1"></i>
                            <?= $colorToEdit ? 'Salvar Alterações' : 'Cadastrar Cor' ?>
                        </button>
                        
                        <?php if (!$colorToEdit): ?>
                            <button type="reset" class="btn btn-outline-danger">
                                <i class="bi bi-eraser me-1"></i> Limpar
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabela de Cores -->
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="bi bi-list-ul me-1"></i>Lista de Cores
                </h2>
            </div>
            <div class="card-body p-0">
                <?php if (empty($colors)): ?>
                    <div class="empty-state">
                        <i class="bi bi-palette"></i>
                        <h3 class="h5">Nenhuma cor cadastrada</h3>
                        <p class="mb-4">Comece cadastrando sua primeira cor</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Cor</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($colors as $color): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($color->id, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="badge-color" style="background-color: <?= htmlspecialchars($color->name, ENT_QUOTES, 'UTF-8') ?>"></span>
                                            <?= htmlspecialchars($color->name, ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="?view=colors&action=edit&id=<?= htmlspecialchars($color->id, ENT_QUOTES, 'UTF-8') ?>" 
                                                   class="btn btn-outline-primary" title="Editar">
                                                   <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?view=colors&action=delete&id=<?= htmlspecialchars($color->id, ENT_QUOTES, 'UTF-8') ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('Tem certeza que deseja excluir esta cor?')"
                                                   title="Excluir">
                                                   <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
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
        // Validação em tempo real
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('name');
            
            if (nameInput) {
                nameInput.addEventListener('input', function() {
                    if (this.value.length > 0 && this.value.length < 2) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            }
        });
    </script>
</body>
</html>