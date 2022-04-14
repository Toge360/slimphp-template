<?php

/* A service class is not a “Manager” or “Utility” class.
Each service class should have only one responsibility, e.g. to transfer money from A to B, and not more. */

namespace App\Domain\Users;
use DI\ContainerBuilder;
use App\Domain\Helpers\Helpers; // the Helper-Methods
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use PDO;

/**
 * Service.
 */

final class UserService {

  private $connection;
  private $userService;
  private $helpers;
  
  /**
   * Constructor.
   *
   * @param PDO $connection The database connection
   */
  public function __construct(PDO $connection, Helpers $helpers) {
    $this->connection = $connection;
    $this->helpers = $helpers;
  }

  public function getUsers(): array {

    /* all user */

    $sql = 'SELECT * FROM users';
    $stmt = $this->connection->prepare ($sql);
    $stmt->execute ();
    $resultArray = $stmt->fetchAll ();

    return (array)$resultArray;

  }


  public function getUser($useruuid): array {

    /* single user */

    $sql = 'SELECT *
    FROM users 
    WHERE uuid = :useruuid';
    $stmt = $this->connection->prepare ($sql);
    $stmt->bindParam (':useruuid', $useruuid);
    $stmt->execute ();
    $resultArray = $stmt->fetchAll ();

    return (array)$resultArray;

  }
    
}