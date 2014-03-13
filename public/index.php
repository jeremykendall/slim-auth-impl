<?php

date_default_timezone_set('UTC');
error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require '../vendor/autoload.php';

use JeremyKendall\Password\PasswordValidator;
use JeremyKendall\Slim\Auth\Adapter\Db\PdoAdapter;
use JeremyKendall\Slim\Auth\Bootstrap;
use JeremyKendall\Slim\Auth\Exception\HttpForbiddenException;

// Create app
$app = new \Slim\Slim(array(
    'templates.path' => '../templates',
    // Debug is set to false to demonstrate custom error handling
    'debug' => false,
    // Default identity storage is session storage. You MUST set the
    // following cookie encryption settings if you use the SessionCookie 
    // middleware, which this example does
    'cookies.encrypt' => true,
    'cookies.secret_key' => 'FZr2ucE7eu5AB31p73QsaSjSIG5jhnssjgABlxlVeNV3nRjLt',
));

// Configure Slim Auth components
$validator = new PasswordValidator();
$adapter = new PdoAdapter(getDb(), 'users', 'username', 'password', $validator);
$acl = new \Example\Acl();
$authBootstrap = new Bootstrap($app, $adapter, $acl);
$authBootstrap->bootstrap();

// Add the session cookie middleware after auth to ensure it's executed first
$app->add(new \Slim\Middleware\SessionCookie());

// Handle the possible 403 the middleware can throw
$app->error(function (\Exception $e) use ($app) {
    if ($e instanceof HttpForbiddenException) {
        return $app->render('403.twig', array('e' => $e), 403);
    }
    // You should handle other exceptions here, not throw them
    throw $e;
});

// Grabbing a few things I want in each view
$app->hook('slim.before.dispatch', function () use ($app) {
    $hasIdentity = $app->auth->hasIdentity();
    $identity = $app->auth->getIdentity();
    $role = ($hasIdentity) ? $identity['role'] : 'guest';
    $memberClass = ($role == 'guest') ? 'danger' : 'success';
    $adminClass = ($role != 'admin') ? 'danger' : 'success';

    $data = array(
        'hasIdentity' => $hasIdentity,
        'role' =>  $role,
        'identity' => $identity,
        'memberClass' => $memberClass,
        'adminClass' => $adminClass,
    );

    $app->view->appendData($data);
});

$app->container->singleton('log', function () {
    $log = new \Monolog\Logger('slim-skeleton');
    $log->pushHandler(new \Monolog\Handler\StreamHandler('../logs/app.log', \Monolog\Logger::DEBUG));

    return $log;
});

// Prepare view
$app->view(new \Slim\Views\Twig());
$app->view->parserOptions = array(
    'charset' => 'utf-8',
    'cache' => realpath('../templates/cache'),
    'auto_reload' => true,
    'strict_variables' => false,
    'autoescape' => true
);
$app->view->parserExtensions = array(new \Slim\Views\TwigExtension());

// Define routes
$app->get('/', function () use ($app) {
    $readme = Parsedown::instance()->parse(
        file_get_contents(dirname(__DIR__) . '/README.md')
    );
    $app->render('index.twig', array('readme' => $readme));
});

$app->get('/member', function () use ($app) {
    $app->render('member.twig');
});

$app->get('/admin', function () use ($app) {
    $app->render('admin.twig');
});

// Login route MUST be named 'login'
$app->map('/login', function () use ($app) {
    $username = null;

    if ($app->request()->isPost()) {
        $username = $app->request->post('username');
        $password = $app->request->post('password');

        $result = $app->authenticator->authenticate($username, $password);

        if ($result->isValid()) {
            $app->redirect('/');
        } else {
            $messages = $result->getMessages();
            $app->flashNow('error', $messages[0]);
        }
    }

    $app->render('login.twig', array('username' => $username));
})->via('GET', 'POST')->name('login');

$app->get('/logout', function () use ($app) {
    if ($app->auth->hasIdentity()) {
        $app->auth->clearIdentity();
    }

    $app->redirect('/');
});

$app->run();

/**
 * Creates database table, users and database connection
 *
 * @return \PDO
 */
function getDb() {
    $dsn = 'sqlite::memory:';
    $options = array(
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    );

    try {
        $db = new \PDO($dsn, null, null, $options);
    } catch (\PDOException $e) {
        die(sprintf('DB connection error: %s', $e->getMessage()));
    }

    $create = 'CREATE TABLE IF NOT EXISTS [users] ( '
        . '[id] INTEGER  NOT NULL PRIMARY KEY, '
        . '[username] VARCHAR(50) NOT NULL, '
        . '[role] VARCHAR(50) NOT NULL, '
        . '[password] VARCHAR(255) NULL)';

    $delete = 'DELETE FROM users';

    $member = 'INSERT INTO users (username, role, password) '
        . "VALUES ('member', 'member', :pass)";

    $admin = 'INSERT INTO users (username, role, password) '
        . "VALUES ('admin', 'admin', :pass)";

    try {
        $db->exec($create);
        $db->exec($delete);
        
        $member = $db->prepare($member);
        $member->execute(array('pass' => password_hash('member', PASSWORD_DEFAULT)));

        $admin = $db->prepare($admin);
        $admin->execute(array('pass' => password_hash('admin', PASSWORD_DEFAULT)));
    } catch (\PDOException $e) {
        die(sprintf('DB setup error: %s', $e->getMessage()));
    }

    return $db;
}
