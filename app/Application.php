<?php

declare(strict_types=1);

namespace Zpm;

use RuntimeException;
use Throwable;

final class Application
{
    private Auth $auth;
    private View $view;

    public function __construct(private array $config, Database $database, string $templatesPath)
    {
        date_default_timezone_set($config['timezone']);
        $this->auth = new Auth($database);
        $this->view = new View($templatesPath);
    }

    public function handle(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $basePath = rtrim($this->config['base_path'], '/');
            if ($basePath !== '' && str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath)) ?: '/';
            }

            if ($method === 'GET' && $path === '/') {
                $this->redirect($this->auth->user() === null ? '/login' : '/dashboard');
            }

            if ($method === 'GET' && $path === '/login') {
                if ($this->auth->user() !== null) {
                    $this->redirect('/dashboard');
                }
                $this->view->render('login', $this->pageData(['error' => $this->flash('error')]));
                return;
            }

            if ($method === 'POST' && $path === '/login') {
                Csrf::verify($_POST['_csrf'] ?? null);
                $email = trim((string) ($_POST['email'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                if ($email === '' || $password === '' || !$this->auth->attempt($email, $password)) {
                    $this->flash('error', 'The email address or password is incorrect.');
                    $this->redirect('/login');
                }
                $this->redirect('/dashboard');
            }

            if ($method === 'POST' && $path === '/logout') {
                Csrf::verify($_POST['_csrf'] ?? null);
                $this->auth->logout();
                $this->redirect('/login');
            }

            if ($method === 'GET' && $path === '/dashboard') {
                $this->requirePermission('dashboard.view');
                $this->view->render('dashboard', $this->pageData());
                return;
            }

            $this->notFound();
        } catch (RuntimeException $exception) {
            $this->renderError($exception->getMessage(), 400);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->renderError('An unexpected error occurred. Please contact the system administrator.', 500);
        }
    }

    private function pageData(array $data = []): array
    {
        return array_merge($data, [
            'appName' => $this->config['app_name'],
            'basePath' => rtrim($this->config['base_path'], '/'),
            'user' => $this->auth->user(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    private function requirePermission(string $permission): void
    {
        if ($this->auth->user() === null) {
            $this->redirect('/login');
        }
        if (!$this->auth->can($permission)) {
            $this->renderError('You do not have permission to access this page.', 403);
            exit;
        }
    }

    private function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $message;
    }

    private function redirect(string $path): void
    {
        header('Location: ' . rtrim($this->config['base_path'], '/') . $path, true, 302);
        exit;
    }

    private function notFound(): void
    {
        $this->renderError('The page you requested was not found.', 404);
    }

    private function renderError(string $message, int $status): void
    {
        http_response_code($status);
        $this->view->render('error', $this->pageData(['message' => $message, 'status' => $status]));
    }
}

