<?php

ini_set('session.cookie_lifetime', 0); // Cookie valide jusqu'à fermeture du navigateur
ini_set('session.gc_maxlifetime', 3600); // Session valide 1h côté serveur
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';");
require_once __DIR__ . '/../vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/Twig');
$twig = new \Twig\Environment($loader, [
    'cache' => false,
]);

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_DB') ?: 'acudb';
    $user = getenv('DB_USER') ?: 'acu';
    $password = getenv('DB_PASSWORD') ?: 'acu';

    $dsn = "pgsql:host={$host};port=5432;dbname={$dbname}";

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ensureUserTable(): void
{
    $pdo = getDbConnection();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_user (
            id SERIAL PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL
        )'
    );

    $adminEmail = getenv('APP_ADMIN_EMAIL') ?: 'admin@aaa.local';
    $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: 'Acupuncture123!';

    $stmt = $pdo->prepare('SELECT 1 FROM app_user WHERE email = :email');
    $stmt->execute([':email' => $adminEmail]);

    if (!$stmt->fetch()) {
        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO app_user (email, password) VALUES (:email, :password)');
        $insert->execute([
            ':email' => $adminEmail,
            ':password' => $passwordHash
        ]);
    }
}

function authenticateUser(string $email, string $password): bool
{
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT password FROM app_user WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    return password_verify($password, $row['password']);
}

function registerUser(string $email, string $password): bool
{
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT 1 FROM app_user WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        return false; // Email déjà utilisé
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare('INSERT INTO app_user (email, password) VALUES (:email, :password)');
    $insert->execute([':email' => $email, ':password' => $passwordHash]);

    return true;
}

function currentUser(): ?string
{
    return $_SESSION['user_email'] ?? null;
}

function isAuthenticated(): bool
{
    return currentUser() !== null;
}

function flash(string $message): void
{
    $_SESSION['flash_message'] = $message;
}

function getFlash(): ?string
{
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }

    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    return $message;
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function getFilters(): array
{
    $pdo = getDbConnection();

    $types = $pdo
        ->query('SELECT DISTINCT "type" FROM patho ORDER BY "type"')
        ->fetchAll(PDO::FETCH_COLUMN);

    $meridians = $pdo
        ->query('SELECT code, nom, element, yin FROM meridien ORDER BY nom')
        ->fetchAll();

    return [
        'types' => $types,
        'meridians' => $meridians,
    ];
}

function getPathologies(?string $type = null, ?string $mer = null): array
{
    $pdo = getDbConnection();

    $where = [];
    $params = [];

    if ($type && $type !== 'all') {
        $where[] = 'p."type" = :type';
        $params[':type'] = $type;
    }

    if ($mer && $mer !== 'all') {
        $where[] = 'p.mer = :mer';
        $params[':mer'] = $mer;
    }

    $sql = '
        SELECT
            p.idP AS idp,
            p.mer,
            p."type" AS type,
            p."desc" AS patho_desc,
            m.nom AS mer_nom,
            m.element,
            m.yin,
            STRING_AGG(DISTINCT s."desc", \' | \' ORDER BY s."desc") AS symptoms
        FROM patho p
        LEFT JOIN meridien m ON m.code = p.mer
        LEFT JOIN symptPatho sp ON sp.idP = p.idP
        LEFT JOIN symptome s ON s.idS = sp.idS
    ';

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= '
        GROUP BY p.idP, p.mer, p."type", p."desc", m.nom, m.element, m.yin
        ORDER BY p.mer, p."type", p.idP
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function getPathoDetail(int $idP): ?array
{
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        'SELECT
            p.idP AS idp,
            p.mer,
            p."type" AS type,
            p."desc" AS patho_desc,
            m.nom AS mer_nom,
            m.element,
            m.yin
        FROM patho p
        LEFT JOIN meridien m ON m.code = p.mer
        WHERE p.idP = :idp'
    );
    $stmt->execute([':idp' => $idP]);

    $patho = $stmt->fetch();

    if (!$patho) {
        return null;
    }

    $stmt2 = $pdo->prepare(
        'SELECT
            s.idS,
            s."desc" AS symptom_desc,
            sp.aggr
         FROM symptPatho sp
         JOIN symptome s ON s.idS = sp.idS
         WHERE sp.idP = :idp
         ORDER BY sp.aggr DESC, s."desc"'
    );
    $stmt2->execute([':idp' => $idP]);
    $patho['symptoms'] = $stmt2->fetchAll();

    $stmt3 = $pdo->prepare(
        'SELECT DISTINCT
            k.idK,
            k.name
         FROM symptPatho sp
         JOIN keySympt ks ON ks.idS = sp.idS
         JOIN keywords k ON k.idK = ks.idK
         WHERE sp.idP = :idp
         ORDER BY k.name'
    );
    $stmt3->execute([':idp' => $idP]);
    $patho['keywords'] = $stmt3->fetchAll();

    return $patho;
}

function searchPathoByKeyword(string $term): array
{
    $pdo = getDbConnection();

    $term = trim($term);
    if ($term === '') {
        return [];
    }

    $words = preg_split('/\s+/', $term);
    $words = array_values(array_filter($words, fn($w) => $w !== ''));

    if (empty($words)) {
        return [];
    }

    $conditions = [];
    $params = [];

    foreach ($words as $i => $word) {
        $param = ':q' . $i;
        $conditions[] = '(LOWER(k.name) LIKE ' . $param . ' OR LOWER(s."desc") LIKE ' . $param . ' OR LOWER(p."desc") LIKE ' . $param . ')';
        $params[$param] = '%' . strtolower($word) . '%';
    }

    $whereClause = implode(' OR ', $conditions);

    $sql = '
        SELECT DISTINCT
            p.idP AS idp,
            p.mer,
            p."type" AS type,
            p."desc" AS patho_desc,
            m.nom AS mer_nom,
            m.element,
            m.yin,
            STRING_AGG(DISTINCT s."desc", \' | \' ORDER BY s."desc") AS symptoms,
            STRING_AGG(DISTINCT k.name, \' | \' ORDER BY k.name) AS keywords
        FROM patho p
        LEFT JOIN meridien m ON m.code = p.mer
        LEFT JOIN symptPatho sp ON sp.idP = p.idP
        LEFT JOIN symptome s ON s.idS = sp.idS
        LEFT JOIN keySympt ks ON ks.idS = s.idS
        LEFT JOIN keywords k ON k.idK = ks.idK
        WHERE ' . $whereClause . '
        GROUP BY p.idP, p.mer, p."type", p."desc", m.nom, m.element, m.yin
        ORDER BY p.mer, p."type", p.idP
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

try {
    ensureUserTable();
} catch (Exception $e) {
    error_log('DB init error: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('Session expirée, veuillez réessayer.');
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm'] ?? '');

        if ($email === '' || $password === '' || $confirm === '') {
            flash("Veuillez remplir tous les champs.");
            header('Location: index.php?page=inscription');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash("Adresse email invalide.");
            header('Location: index.php?page=inscription');
            exit;
        }

        if ($password !== $confirm) {
            flash("Les mots de passe ne correspondent pas.");
            header('Location: index.php?page=inscription');
            exit;
        }

        if (strlen($password) < 8) {
            flash("Le mot de passe doit contenir au moins 8 caractères.");
            header('Location: index.php?page=inscription');
            exit;
        }

        try {
            if (registerUser($email, $password)) {
                session_regenerate_id(true);
                $_SESSION['user_email'] = $email;
                flash("Inscription réussie, bienvenue !");
                header('Location: index.php?page=pathologies');
                exit;
            } else {
                flash("Cet email est déjà utilisé.");
                header('Location: index.php?page=inscription');
                exit;
            }
        } catch (Exception $e) {
            flash("Erreur lors de l'inscription.");
            header('Location: index.php?page=inscription');
            exit;
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            flash('Veuillez renseigner l\’email et le mot de passe.');
            header('Location: index.php');
            exit;
        }

        try {
            if (authenticateUser($email, $password)) {
                session_regenerate_id(true);
                $_SESSION['user_email'] = $email;
                flash('Connexion réussie.');
                header('Location: index.php?page=pathologies');
                exit;
            }
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
        }

        flash('Identifiants invalides.');
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'logout') {
        session_destroy();
        session_start();
        flash('Déconnexion effectuée.');
        header('Location: index.php');
        exit;
    }
}

$page = $_GET['page'] ?? 'accueil';
$flashMessage = getFlash();

$csrfToken = generateCsrfToken();

$params = [
    'currentPage' => $page,
    'isAuthenticated' => isAuthenticated(),
    'userEmail' => currentUser(),
    'flashMessage' => $flashMessage,
    'csrfToken' => $csrfToken,
];

switch ($page) {
    case 'pathologies':
        $filterType = $_GET['type'] ?? 'all';
        $filterMer = $_GET['mer'] ?? 'all';

        $params['pathologies'] = getPathologies($filterType, $filterMer);
        $params['filters'] = getFilters();
        $params['selectedType'] = $filterType;
        $params['selectedMer'] = $filterMer;

        echo $twig->render('pathologies.html.twig', $params);
        break;

    case 'patho':
        $idP = intval($_GET['id'] ?? 0);
        $patho = getPathoDetail($idP);

        if (!$patho) {
            flash('Pathologie non trouvée.');
            header('Location: index.php?page=pathologies');
            exit;
        }

        $params['patho'] = $patho;

        echo $twig->render('patho.html.twig', $params);
        break;

    case 'inscription':
        echo $twig->render('inscription.html.twig', $params);
        break;
    case 'accueil':
    default:
        $term = trim($_GET['keyword'] ?? '');

        $params['keyword'] = $term;
        $params['homePathologies'] = getPathologies('all', 'all');
        $params['searchResults'] = $term !== '' ? searchPathoByKeyword($term) : [];

        echo $twig->render('index.html.twig', $params);
        break;
}
