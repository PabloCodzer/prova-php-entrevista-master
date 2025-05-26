<?php

require_once __DIR__ . '/Connection.php';
$connection = new Connection();

$error = '';
$success = '';
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';  
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userToEdit = null;

// formulário não lembro pq filtra o body......
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $postId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    
    $errors = [];
    
    if (empty($name)) 
    {
        $errors['name'] = "Nome é obrigatório.";
    } 
    elseif (strlen($name) < 3) 
    {
        $errors['name'] = "Nome deve ter pelo menos 3 caracteres.";
    } 
    elseif (strlen($name) > 100) 
    {
        $errors['name'] = "Nome deve ter no máximo 100 caracteres.";
    }
    
    if (empty($email)) 
    {
        $errors['email'] = "Email é obrigatório.";
    } 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
    {
        $errors['email'] = "Email inválido.";
    } 
    elseif (strlen($email) > 255) 
    {
        $errors['email'] = "Email deve ter no máximo 255 caracteres.";
    }

    if (empty($errors)) 
    {
        try {
            if (isset($_POST['create'])) 
            {
                // Verifica duplicatas
                $stmtCheck = $connection->prepareAndExecute(
                    "SELECT COUNT(*) FROM users WHERE email = :email OR name = :name",
                    [':email' => $email, ':name' => $name]
                );
                
                if ($stmtCheck->fetchColumn() > 0) 
                {
                    $stmtCheck = $connection->prepareAndExecute(
                        "SELECT COUNT(*) FROM users WHERE email = :email",
                        [':email' => $email]
                    );
                    if ($stmtCheck->fetchColumn() > 0) 
                    {
                        $errors['email'] = "Este email já está cadastrado.";
                    } 
                    else 
                    {
                        $errors['name'] = "Este nome já está cadastrado.";
                    }
                } 
                else 
                {
                    $connection->prepareAndExecute(
                        "INSERT INTO users (name, email) VALUES (:name, :email)",
                        [':name' => $name, ':email' => $email]
                    );
                    $_SESSION['success_message'] = "Usuário criado com sucesso!";
                    header("Location: index.php?view=users");
                    exit;
                }
            } 
            elseif (isset($_POST['edit']) && $postId) 
            {
                // Verifica duplicatas excluindo o atual
                $stmtCheck = $connection->prepareAndExecute(
                    "SELECT COUNT(*) FROM users WHERE (email = :email OR name = :name) AND id != :id",
                    [':email' => $email, ':name' => $name, ':id' => $postId]
                );
                
                if ($stmtCheck->fetchColumn() > 0) 
                {
                    $stmtCheck = $connection->prepareAndExecute(
                        "SELECT COUNT(*) FROM users WHERE email = :email AND id != :id",
                        [':email' => $email, ':id' => $postId]
                    );
                    if ($stmtCheck->fetchColumn() > 0) 
                    {
                        $errors['email'] = "Este email já está cadastrado para outro usuário.";
                    } 
                    else 
                    {
                        $errors['name'] = "Este nome já está cadastrado para outro usuário.";
                    }
                } 
                else 
                {
                    $connection->prepareAndExecute(
                        "UPDATE users SET name = :name, email = :email WHERE id = :id",
                        [':name' => $name, ':email' => $email, ':id' => $postId]
                    );
                    $_SESSION['success_message'] = "Usuário atualizado com sucesso!";
                    header("Location: index.php?view=users");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = "Erro ao salvar usuário: " . $e->getMessage();
        }
    }
}

// ..deleta usu
if ($action === 'delete' && $id) 
{
    try {
        $connection->prepareAndExecute(
            "DELETE FROM users WHERE id = :id",
            [':id' => $id]
        );
        $_SESSION['success_message'] = "Usuário excluído com sucesso!";
        header("Location: index.php?view=users");
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao excluir usuário: " . $e->getMessage();
    }
} 
elseif ($action === 'edit' && $id) 
{
    try {
        $stmt = $connection->prepareAndExecute(
            "SELECT * FROM users WHERE id = :id",
            [':id' => $id]
        );
        $userToEdit = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$userToEdit) 
        {
            $error = "Usuário não encontrado.";
        }
    } catch (PDOException $e) {
        $error = "Erro ao carregar usuário: " . $e->getMessage();
    }
}

// Carregar lista de usuários
try {
    $users = $connection->prepareAndExecute("SELECT * FROM users ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $error = "Erro ao carregar usuários: " . $e->getMessage();
    $users = [];
}

// Recuperar mensagem da sessão
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
    <title>Gerenciamento de Usuários</title>
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
        
        .table {
            margin-bottom: 0;
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
        
        .btn-outline-secondary {
            color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .invalid-feedback {
            display: block;
        }
        
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .floating-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-size: 24px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.4);
        }
        
        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
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
    </style>
</head>
<body>
    <div class="container py-5">
            <a href="index.php" class="link-back">
                    <i class="bi bi-arrow-left me-2"></i>Voltar
            </a>
        <div class="d-flex justify-content-between align-items-center mb-4">

            <h1 class="page-title">
                <i class="bi bi-people-fill me-2"></i>Gerenciamento de Usuários
            </h1>
            <?php if (!$userToEdit): ?>
                <button class="btn btn-primary" id="btnShowForm">
                    <i class="bi bi-plus-lg me-1"></i>Novo Usuário
                </button>
            <?php endif; ?>
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
        <div id="formContainer" class="<?= $userToEdit ? '' : 'd-none' ?>">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">
                        <i class="bi bi-person-<?= $userToEdit ? 'check' : 'plus' ?> me-1"></i>
                        <?= $userToEdit ? 'Editar Usuário' : 'Novo Usuário' ?>
                    </h2>
                    <a href="index.php?view=users" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <form method="post" id="userForm" novalidate>
                        <input type="hidden" name="id" value="<?= $userToEdit->id ?? '' ?>">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="form-label">
                                        <i class="bi bi-person-fill me-1"></i>Nome completo
                                    </label>
                                    <input type="text" name="name" id="name" 
                                           class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                                           value="<?= isset($userToEdit->name) ? htmlspecialchars($userToEdit->name, ENT_QUOTES, 'UTF-8') : ($_POST['name'] ?? '') ?>" 
                                           required>
                                    <?php if (isset($errors['name'])): ?>
                                        <div class="invalid-feedback">
                                            <i class="bi bi-exclamation-circle me-1"></i>
                                            <?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        <i class="bi bi-envelope-fill me-1"></i>E-mail
                                    </label>
                                    <input type="email" name="email" id="email" 
                                           class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                           value="<?= isset($userToEdit->email) ? htmlspecialchars($userToEdit->email, ENT_QUOTES, 'UTF-8') : ($_POST['email'] ?? '') ?>" 
                                           required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback">
                                            <i class="bi bi-exclamation-circle me-1"></i>
                                            <?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="<?= $userToEdit ? 'edit' : 'create' ?>" 
                                    class="btn btn-success me-2">
                                <i class="bi bi-check-lg me-1"></i>
                                <?= $userToEdit ? 'Salvar Alterações' : 'Cadastrar Usuário' ?>
                            </button>
                            
                            <a href="index.php?view=users" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg me-1"></i>Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tabela de Usuários -->
        <div id="userTableContainer" class="<?= $userToEdit ? 'd-none' : '' ?>">
            <?php if (empty($users)): ?>
                <div class="card">
                    <div class="card-body empty-state">
                        <i class="bi bi-people"></i>
                        <h3 class="h5">Nenhum usuário cadastrado</h3>
                        <p class="mb-4">Comece cadastrando seu primeiro usuário</p>
                        <button class="btn btn-primary" id="btnShowFormEmptyState">
                            <i class="bi bi-plus-lg me-1"></i>Novo Usuário
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">
                            <i class="bi bi-list-ul me-1"></i>Lista de Usuários
                        </h2>
                        <span class="badge bg-primary rounded-pill">
                            <?= count($users) ?> <?= count($users) === 1 ? 'usuário' : 'usuários' ?>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user->id, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar me-3">
                                                        <span class="avatar-initial bg-primary text-white rounded-circle">
                                                            <?= strtoupper(substr($user->name, 0, 1)) ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8') ?></h6>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($user->email, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end action-buttons">
                                                <div class="btn-group">
                                                    <a href="?view=users&action=edit&id=<?= htmlspecialchars($user->id, ENT_QUOTES, 'UTF-8') ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="Editar usuário">
                                                       <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="?view=users&action=delete&id=<?= htmlspecialchars($user->id, ENT_QUOTES, 'UTF-8') ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Tem certeza que deseja excluir este usuário?')"
                                                       title="Excluir usuário">
                                                       <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botão Flutuante (Mobile) -->
    <button class="btn btn-primary floating-btn d-lg-none" id="floatingBtn">
        <i class="bi bi-plus"></i>
    </button>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elementos
            const btnShowForm = document.getElementById('btnShowForm');
            const btnShowFormEmptyState = document.getElementById('btnShowFormEmptyState');
            const btnCancelForm = document.getElementById('btnCancelForm');
            const floatingBtn = document.getElementById('floatingBtn');
            const form = document.getElementById('formContainer');
            const table = document.getElementById('userTableContainer');
            
            // Mostrar formulário
            function showForm() {
                form.classList.remove('d-none');
                table.classList.add('d-none');
                if (btnShowForm) btnShowForm.classList.add('d-none');
                if (floatingBtn) floatingBtn.classList.add('d-none');
                document.getElementById('name').focus();
            }
            
            // Esconder formulário
            function hideForm() {
                form.classList.add('d-none');
                table.classList.remove('d-none');
                if (btnShowForm) btnShowForm.classList.remove('d-none');
                if (floatingBtn) floatingBtn.classList.remove('d-none');
                document.getElementById('userForm').reset();
            }
            
            // Event Listeners
            if (btnShowForm) btnShowForm.addEventListener('click', showForm);
            if (btnShowFormEmptyState) btnShowFormEmptyState.addEventListener('click', showForm);
            if (floatingBtn) floatingBtn.addEventListener('click', showForm);
            
            // Validação em tempo real
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            
            if (nameInput) {
                nameInput.addEventListener('input', function() {
                    if (this.value.length > 0 && this.value.length < 3) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            }
            
            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (this.value.length > 0 && !emailRegex.test(this.value)) 
                    {
                        this.classList.add('is-invalid');
                    } 
                    else 
                    {
                        this.classList.remove('is-invalid');
                    }
                });
            }
            
            // Verificar se o form ta vazoi
            <?php if (!empty($errors)): ?>
                showForm();
            <?php endif; ?>
        });
    </script>
</body>
</html>