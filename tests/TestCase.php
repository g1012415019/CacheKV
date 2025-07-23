<?php

declare(strict_types=1);

namespace Asfop\QueryCache\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PDO;

class TestCase extends BaseTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create tables
        $this->pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
        $this->pdo->exec("CREATE TABLE user_info (user_id INTEGER PRIMARY KEY, email TEXT, bio TEXT)");

        // Insert some data
        $this->pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");
        $this->pdo->exec("INSERT INTO users (id, name) VALUES (2, 'Bob')");
        $this->pdo->exec("INSERT INTO users (id, name) VALUES (3, 'Charlie')");

        $this->pdo->exec("INSERT INTO user_info (user_id, email, bio) VALUES (1, 'alice@example.com', 'Software Engineer')");
        $this->pdo->exec("INSERT INTO user_info (user_id, email, bio) VALUES (2, 'bob@example.com', 'Project Manager')");
        $this->pdo->exec("INSERT INTO user_info (user_id, email, bio) VALUES (3, 'charlie@example.com', 'Designer')");
    }

    protected function tearDown(): void
    {
        // $this->pdo = null; // Removed to avoid TypeError in PHP 7.4+
        parent::tearDown();
    }
}