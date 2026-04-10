<?php

ini_set('session.cookie_lifetime', 0); // Cookie valide jusqu'à fermeture du navigateur
ini_set('session.gc_maxlifetime', 3600); // Session valide 1h côté serveur
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => false, // true si HTTPS
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
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

function ensureUserTables(): void
{
    $pdo = getDbConnection();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_user (
            id SERIAL PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS remember_token (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES app_user(id) ON DELETE CASCADE,
            selector VARCHAR(32) NOT NULL UNIQUE,
            hashed_validator VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL
        )'
    );

    // $stmt = $pdo->prepare('SELECT 1 FROM app_user WHERE email = :email');
    // $stmt->execute([':email' => 'admin@aaa.local']);

    // if (!$stmt->fetch()) {
    //     $passwordHash = password_hash('Acupuncture123!', PASSWORD_DEFAULT);
    //     $insert = $pdo->prepare('INSERT INTO app_user (email, password) VALUES (:email, :password)');
    //     $insert->execute([
    //         ':email' => 'admin@aaa.local',
    //         ':password' => $passwordHash
    //     ]);
    // }
}

function findUserByEmail(string $email): ?array
{
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT id, email, password FROM app_user WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function authenticateUser(string $email, string $password): ?array
{
    $user = findUserByEmail($email);

    if (!$user) {
        return null;
    }

    if (!password_verify($password, $user['password'])) {
        return null;
    }

    return $user;
}

function registerUser(string $email, string $password): bool
{
    $pdo = getDbConnection();

    if (findUserByEmail($email)) {
        return false;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('
        INSERT INTO app_user (email, password)
        VALUES (:email, :password)
    ');

    $stmt->execute([
        ':email' => $email,
        ':password' => $passwordHash
    ]);

    return true;
}

function createRememberMeToken(int $userId): void
{
    $pdo = getDbConnection();

    $selector = bin2hex(random_bytes(8));
    $validator = bin2hex(random_bytes(32));
    $hashedValidator = password_hash($validator, PASSWORD_DEFAULT);

    $expiresAt = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('
        INSERT INTO remember_token (user_id, selector, hashed_validator, expires_at)
        VALUES (:user_id, :selector, :hashed_validator, :expires_at)
    ');

    $stmt->execute([
        ':user_id' => $userId,
        ':selector' => $selector,
        ':hashed_validator' => $hashedValidator,
        ':expires_at' => $expiresAt,
    ]);

    $cookieValue = $selector . ':' . $validator;

    setcookie('remember_me', $cookieValue, [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => '/',
        'secure'   => false, // mettre true en HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}


function loginFromRememberMe(): void
{
    if (isset($_SESSION['user_email'])) {
        return;
    }

    if (empty($_COOKIE['remember_me'])) {
        return;
    }

    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) !== 2) {
        return;
    }

    [$selector, $validator] = $parts;

    $pdo = getDbConnection();

    $stmt = $pdo->prepare('
        SELECT rt.id, rt.user_id, rt.hashed_validator, rt.expires_at, u.email
        FROM remember_token rt
        JOIN app_user u ON u.id = rt.user_id
        WHERE rt.selector = :selector
        LIMIT 1
    ');
    $stmt->execute([':selector' => $selector]);
    $row = $stmt->fetch();

    if (!$row) {
        return;
    }

    if (strtotime($row['expires_at']) < time()) {
        deleteRememberMeToken($selector);
        return;
    }

    if (!password_verify($validator, $row['hashed_validator'])) {
        deleteRememberMeToken($selector);
        return;
    }

    $_SESSION['user_email'] = $row['email'];
    $_SESSION['user_id'] = $row['user_id'];

    deleteRememberMeToken($selector);
    createRememberMeToken((int)$row['user_id']);
}


function deleteRememberMeToken(?string $selector = null): void
{
    $pdo = getDbConnection();

    if ($selector !== null) {
        $stmt = $pdo->prepare('DELETE FROM remember_token WHERE selector = :selector');
        $stmt->execute([':selector' => $selector]);
    }

    setcookie('remember_me', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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
    ensureUserTables();
} catch (Exception $e) {
        // ignore
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        if ($email === '' || $password === '') {
            flash('Veuillez renseigner l\'email et le mot de passe.');
            header('Location: index.php');
            exit;
        }

        try {
            $user = authenticateUser($email, $password);

            if ($user) {
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_id'] = $user['id'];

                if ($remember) {
                    createRememberMeToken((int)$user['id']);
                }

                flash('Connexion réussie.');
                header('Location: index.php?page=accueil');
                exit;
            }
        } catch (Exception $e) {
            // ignore
        }

        flash('Identifiants invalides.');
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm'] ?? '');

        if ($email === '' || $password === '' || $confirm === '') {
            flash('Veuillez remplir tous les champs.');
            header('Location: index.php?page=inscription');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Adresse email invalide.');
            header('Location: index.php?page=inscription');
            exit;
        }

        if (strlen($password) < 8) {
            flash('Le mot de passe doit contenir au moins 8 caractères.');
            header('Location: index.php?page=inscription');
            exit;
        }

        if ($password !== $confirm) {
            flash('Les mots de passe ne correspondent pas.');
            header('Location: index.php?page=inscription');
            exit;
        }

        try {
            if (registerUser($email, $password)) {
                $user = authenticateUser($email, $password);
                if ($user) {
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_id'] = $user['id'];
                }
                flash('Inscription réussie ! Bienvenue.');
                header('Location: index.php?page=accueil');
                exit;
            } else {
                flash('Un compte avec cet email existe déjà.');
                header('Location: index.php?page=inscription');
                exit;
            }
        } catch (Exception $e) {
            flash('Erreur lors de l\'inscription. Veuillez réessayer.');
            header('Location: index.php?page=inscription');
            exit;
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'logout') {
        if (!empty($_COOKIE['remember_me'])) {
            $parts = explode(':', $_COOKIE['remember_me'], 2);
            if (count($parts) === 2) {
                deleteRememberMeToken($parts[0]);
            }
        }

        session_unset();
        session_destroy();
        session_start();

        flash('Déconnexion effectuée.');
        header('Location: index.php');
        exit;
    }
}

$page = $_GET['page'] ?? 'accueil';
$flashMessage = getFlash();

$params = [
    'currentPage' => $page,
    'isAuthenticated' => isAuthenticated(),
    'userEmail' => currentUser(),
    'flashMessage' => $flashMessage,
];

switch ($page) {
    case 'patho':
        $idP = intval($_GET['id'] ?? 0);
        $patho = getPathoDetail($idP);
        if (!$patho) {
            header('Location: index.php?page=accueil');
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
        // 1. On récupère TOUJOURS les listes pour les menus déroulants (Type/Méridien)
        $params['filters'] = getFilters(); 

        // 2. On récupère les entrées de l'utilisateur
        $term = trim($_GET['keyword'] ?? '');
        $filterType = $_GET['type'] ?? 'all';
        $filterMer = $_GET['mer'] ?? 'all';

        $params['keyword'] = $term;
        $params['selectedType'] = $filterType;
        $params['selectedMer'] = $filterMer;

        // 3. Logique d'affichage
        if ($term !== '') {
            // Si l'utilisateur a tapé un mot-clé
            $params['searchResults'] = searchPathoByKeyword($term);
        } else {
            // Sinon, on affiche soit tout, soit les résultats filtrés par Type/Méridien
            $params['homePathologies'] = getPathologies($filterType, $filterMer);
            $params['searchResults'] = [];
        }

        echo $twig->render('index.html.twig', $params);
        break;
            
}
