<?php

/**
 * Parrot PHP Framework - Base Model (PDO)
 *
 * Model base usando PDO puro (sem Eloquent).
 * Fornece métodos básicos de CRUD.
 *
 * Esta é uma implementação simples que usa PDO diretamente.
 * Para aplicações maiores, recomenda-se usar EloquentModel.
 *
 * @see EloquentModel Versão com Laravel Eloquent ORM
 * @see https://www.php.net/manual/pt_BR/book.pdo.php PDO - PHP Data Objects
 */

namespace App\Models;

use PDO;
use PDOStatement;

/**
 * Model Base com PDO
 *
 * Classe abstrata para acesso direto ao banco via PDO.
 * Fornece métodos utilitários para:
 * - Buscar registros (all, find, where)
 * - Paginação
 * - Inserção, atualização, exclusão
 * - Transações
 *
 * Cada subclasse deve definir a propriedade $table.
 *
 * @package App\Models
 */
abstract class Model
{
    /** @var PDO Conexão com o banco de dados */
    protected PDO $pdo;

    /** @var string Nome da tabela no banco de dados */
    protected string $table = '';

    /**
     * Construtor
     *
     * @param PDO $pdo Conexão PDO com o banco
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca todos os registros da tabela
     *
     * @return array Array de registros
     */
    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }

    /**
     * Paginação de registros
     *
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Registros por página
     * @return array Registros da página
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
     * Conta total de registros
     *
     * @return int Total de registros
     */
    public function count(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function where(string $column, mixed $value): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    public function firstWhere(string $column, mixed $value): ?array
    {
        $results = $this->where($column, $value);
        return $results[0] ?? null;
    }

    public function create(array $data): int
    {
        foreach (array_keys($data) as $column) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                throw new \InvalidArgumentException("Invalid column name: {$column}");
            }
        }
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        foreach (array_keys($data) as $column) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                throw new \InvalidArgumentException("Invalid column name: {$column}");
            }
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

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Executa uma query personalizada
     *
     * Útil para queries complexas que não são cobertas pelos métodos above.
     *
     * @param string $sql Query SQL com placeholders
     * @param array $params Parâmetros para a query
     * @return PDOStatement Statement preparado
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Inicia uma transação
     *
     * Transações garantem que um conjunto de operações
     * seja executado atomicamente - ou tudo ou nada.
     *
     * @return void
     * @see commit()
     * @see rollBack()
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Confirma a transação
     *
     * @return void
     * @see beginTransaction()
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Desfaz a transação
     *
     * @return void
     * @see beginTransaction()
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }
}
