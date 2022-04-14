<?php

/* A service class is not a “Manager” or “Utility” class.
Each service class should have only one responsibility, e.g. to transfer money from A to B, and not more. */

namespace App\Domain\Token\Service;

use PDO;

/**
 * Service.
 */

final class CompanyPermissionValidation {

    private $connection;

    /**
     * Constructor.
     *
     * @param PDO $connection The database connection
     */
    public function __construct(PDO $connection) {
        $this->connection = $connection;
    }

    public function checkCompanyPermission($token,$companyUuid): array {

      if (!empty($token) AND !empty($companyUuid)) {

        $sql = 'SELECT sessions.usersId 
        FROM sessions 
        JOIN usersCompanies ON sessions.usersId = usersCompanies.userId
        WHERE sessions.token=:token';

        $stmt = $this->connection->prepare ($sql);
        $stmt->bindParam (':token', $token);
        $stmt->execute ();
        $resultArray = $stmt->fetchAll ();

        if (count($resultArray) < 1) {

          // no result, no permission

          $response = array(
            'valid' => false,
            'reason' => 'no permissions for company'
          );

          return (array)$response;

        } else {

          $response = array(
            'companyPermission' => true
          );

          return (array)$response;

        }

      } else {

        // no token
        $response = array(
          'valid' => false,
          'reason' => 'credentials missing'
        );

        return (array)$response;

      }

        

    }
    
}