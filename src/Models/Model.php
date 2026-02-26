<?php

namespace App\Models;

use PDO;
use PDOStatement;

/**
 * Classe abstrata base para todos os Models.
 * Fornece métodos utilitários para operações com banco de dados.
 */
abstract class Model
{
    /**
     * Conexão PDO.
     */
    protected PDO $pdo;

    /**
     * Nome da tabela (deve ser sobrescrito nas classes filhas).
     */
    protected string $table = '';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca todos os registros da tabela.
     * WARNING: Use paginate() para evitar estouro de memória com tabelas grandes.
     */
    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }

    /**
     * Busca registros com paginação.
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Conta o total de registros na tabela.
     */
    public function count(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Busca um registro pelo ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Busca registros por uma condição WHERE.
     */
    public function where(string $column, mixed $value): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    /**
     * Busca o primeiro registro por uma condição.
     */
    public function firstWhere(string $column, mixed $value): ?array
    {
        $results = $this->where($column, $value);
        return $results[0] ?? null;
    }

    /**
     * Insere um novo registro.
     * Retorna o ID inserido.
     */
    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualiza um registro pelo ID.
     * Retorna true se atualizou, false se não encontrou.
     */
    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $sets = implode(', ', array_map(
            fn($key) => "{$key} = ?",
            array_keys($data)
        ));

        $sql = "UPDATE {$this->table} SET {$sets} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([...array_values($data), $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Remove um registro pelo ID.
     * Retorna true se removeu, false se não encontrou.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Executa uma query personalizada (para queries complexas).
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Inicia uma transação.
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Confirma uma transação.
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Cancela uma transação.
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }
}
