<?php
/**
 * Purpose: Handles user login, authentication, and redirection to the appropriate dashboard.
 * Path: /uwuweb/index.php
 */

require_once 'includes/auth.php';
require_once 'includes/db.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) $error = 'Uporabniško ime in geslo sta obvezna.'; else try {
        $pdo = getDBConnection();
        if (!$pdo) {
            error_log("Database connection failed in index.php login");
            $error = 'Sistemska napaka: Povezava z bazo podatkov ni uspela. Poskusite znova kasneje.';
        } else {
            $stmt = $pdo->prepare("SELECT user_id, username, pass_hash, role_id FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['pass_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['last_activity'] = time();

                $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                unset($_SESSION['redirect_after_login']);
                header("Location: $redirect");
                exit;
            }

            $error = 'Neveljavno uporabniško ime ali geslo.';
        }
    } catch (PDOException $e) {
        error_log("Database error during login: " . $e->getMessage());
        $error = 'Napaka pri prijavi. Poskusite znova.';
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Prijava - uwuweb</title>
    <link rel="stylesheet" href="/uwuweb/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body class="bg-primary text-primary quicksand-font">

<div class="d-flex justify-center items-center" style="min-height: 100vh;">
    <div class="card card-entrance shadow-lg p-lg w-full" style="max-width: 400px;">
        <div class="text-center mb-lg">
            <img src="/uwuweb/design/uwuweb-logo.png" width="100" height="100" alt="uwuweb Logo"
                 class="d-block mx-auto">
            <h1 class="text-xxl font-bold text-accent mb-sm">uwuweb</h1>
            <p class="text-secondary">Sistem za upravljanje ocen</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert status-error mb-md">
                <div class="alert-content">
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
            <div class="alert status-success mb-md">
                <div class="alert-content">
                    Uspešno ste se odjavili.
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'session_expired'): ?>
            <div class="alert status-warning mb-md">
                <div class="alert-content">
                    Vaša seja je potekla zaradi neaktivnosti. Prosimo, prijavite se znova.
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="/uwuweb/index.php" class="mt-md">
            <div class="form-group mb-md">
                <label for="username" class="form-label mb-xs d-block">Uporabniško ime</label>
                <input type="text" id="username" name="username" class="form-input"
                       value="<?= htmlspecialchars($username) ?>" required autocomplete="username" autofocus>
            </div>

            <div class="form-group mb-lg">
                <label for="password" class="form-label mb-xs d-block">Geslo</label>
                <input type="password" id="password" name="password" class="form-input"
                       required autocomplete="current-password">
            </div>

            <div class="form-group mt-lg">
                <button type="submit" class="btn btn-primary btn-lg w-full">
                    <span class="btn-icon mr-sm">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                    </span>
                    Prijava
                </button>
            </div>
        </form>
        <p class="text-center text-secondary text-sm mt-lg mb-0">© <?= date('Y') ?> uwuweb</p>
    </div>
</div>

<script src="/assets/js/main.js"></script>
</body>
</html>
