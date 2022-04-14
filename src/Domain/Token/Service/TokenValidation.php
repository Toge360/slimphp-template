<?php

/* A service class is not a “Manager” or “Utility” class.
Each service class should have only one responsibility, e.g. to transfer money from A to B, and not more. */

namespace App\Domain\Token\Service;

use PDO;

/**
 * Service.
 */

final class TokenValidation {

    private $connection;

    /**
     * Constructor.
     *
     * @param PDO $connection The database connection
     */
    public function __construct(PDO $connection) {
        $this->connection = $connection;
    }


    public function getuserData($token): array {


      $sql = 'SELECT sessions.usersId,
      users.usersType
      FROM sessions 
      JOIN users ON sessions.usersId = users.id
      WHERE sessions.token = :token';

      $stmt = $this->connection->prepare ($sql);
      $stmt->bindParam (':token', $token);
      $stmt->execute ();
      $resultArray = $stmt->fetchAll ();

      return (array)$resultArray;

    }

    public function getUsersPermissions($userUuid,$permissionPlugin,$userData,$companyUuid): array {

      /* Generell hat ein Admin (nicht Sub-Admin) alle Rechten (read, write, delete).
      Hier wird nur geprüft, ob der Admin auch die Firma verwalten darf. */

      //print_r($userData);
      //echo $companyUuid;
      //echo $userUuid;

      if (!empty($userUuid) 
      AND !empty($userData)) {

        if ($userData[0]['usersType'] == '1') {

          // master
          // A master has permission for every company

          $response = array(
            'permission' => true,
            'isMaster' => '1',
            'read' => '1',
            'write' => '1',
            'delete' => '1',
            'master' => '1',
          );

          
        } else if ($userData[0]['usersType'] == '2') {

          // regular admin
          // admins must be given rights to companies by a master

          $sql = 'SELECT * FROM usersCompanies 
          WHERE userUuid=:userUuid 
          AND companyUuid=:companyUuid';

          $stmt = $this->connection->prepare ($sql);
          $stmt->bindParam (':userUuid', $userUuid);
          $stmt->bindParam (':companyUuid', $companyUuid);
          $stmt->execute ();
          $resultArray = $stmt->fetchAll ();

          if (count($resultArray) > 0) {

            // admin has permission for company

            $response = array(
              'permission' => true,
              'isMaster' => '0',
              'read' => '1',
              'write' => '1',
              'delete' => '1',
              'master' => '0',
            );

          } else {

            // admin has no permission for company

            $response = array(
              'permission' => false,
              'isMaster' => '0',
              'read' => '0',
              'write' => '0',
              'delete' => '0',
              'master' => '0',
            );

          }

        } else if ($userData[0]['usersType'] == '3') {

          // todo: Sub-Admin
          // sub-admins must be given rights to plugins(!) by a admin

        } else {

          // unknown type

          $response = array(
            'permission' => false,
            'isMaster' => '0',
            'read' => '0',
            'write' => '0',
            'delete' => '0',
            'master' => '0',
          );

        }
        
        
      } else {

        // no userUuid and/or no $userData

        $response = array(
          'permission' => false,
          'isMaster' => '0',
          'read' => '0',
          'write' => '0',
          'delete' => '0',
          'master' => '0',
        );

      }

      return (array)$response;

    }




    public function getUsersCompanyPermissions($userUuid,$companyUuid): bool {


      $sql = 'SELECT * FROM usersCompanies 
      WHERE userUuid=:userUuid 
      AND companyUuid=:companyUuid';
      $stmt = $this->connection->prepare ($sql);
      $stmt->bindParam (':userUuid', $userUuid);
      $stmt->bindParam (':companyUuid', $companyUuid);
      $stmt->execute ();
      $resultArray = $stmt->fetchAll ();

      if (count($resultArray) < 1) {

        $response = false;

      } else {

        $response = true;

      }

      return (bool)$response;
    }



    public function extendToken($token): String { // Token Ablaufdatum verlängern

      // update token expiredate
      $newExpireDate = date("Y-m-d H:i:s", strtotime("+30 minutes")); // add 30 minutes to expire
      $sql = "UPDATE sessions SET expires = :newExpireDate WHERE token=:token";
      $stmt = $this->connection->prepare ($sql);
      $stmt->bindParam (':token', $token);
      $stmt->bindParam (':newExpireDate', $newExpireDate);
      $stmt->execute ();

      return (String)$newExpireDate;

    }


    public function tokenValidity($token): array {

      if (!empty($token)) {

        $sql = 'SELECT sessions.*,
        users.uuid AS userUuid
        FROM sessions 
        JOIN users ON sessions.usersId = users.id
        WHERE sessions.token=:token';
        
        $stmt = $this->connection->prepare ($sql);
        $stmt->bindParam (':token', $token);
        $stmt->execute ();
        $resultArray = $stmt->fetchAll ();

        if (count($resultArray) < 1) { // no result, no permission

          $response = array(
            'valid' => false,
            'reason' => 'session does not exist'
          );

          return (array)$response;

        } else { // check expires

          $now = time();

          if ($now > strtotime($resultArray[0]['expires'])) {

            // token expired
            $response = array(
              'valid' => false,
              'reason' => 'session expired'
            );
  
            return (array)$response;

          } else { // token okay

            $newExpireDate = $this->extendToken($token); // Token Ablaufdatum verlängern

            $response = array(
              'valid' => true,
              'usersId' => $resultArray[0]['usersId'],
              'userUuid' => $resultArray[0]['userUuid'],
              'sessionExpires' => $newExpireDate,
            );

            return (array)$response;

          }

        }

      } else {

        // no token
        $response = array(
          'valid' => false,
          'reason' => 'token missing'
        );

        return (array)$response;

      }

    }


    public function validateToken($token,$permissionPlugin,$companyuuid): array { 

      // Gültigkeit des Tokens checken (expires)
      // $permissionPlugin: in $arguments['permissionPlugin'] (ApiTokenMiddleware.php) we got an id of that plugin. With that Plugin we can check, if user has permissions to do that

      $userData = null;
      $tokenValidity = $this->tokenValidity($token);

      if ($tokenValidity['valid']) {

        $userData = $this->getuserData($token); // get userData ... 1 = Master, 2 = Admin, 3 = Subadmin

        $usersId = $tokenValidity['usersId'];
        $userUuid = $tokenValidity['userUuid'];

        if (!empty($permissionPlugin)) {

          $usersPermissions = $this->getUsersPermissions($userUuid,$permissionPlugin,$userData,$companyuuid);
  
        } else {
  
          $usersPermissions = array(
            'permission' => true,
            'read' => "1",
            'write' => "1"
          );
  
        }

        /* if ($usersPermissions['permission']) { // nur wenn true
          if (!empty($companyuuid) AND $userData[0]['usersType'] != '1') {
            // check if user got company permissions
            $companyPermission = $this->getUsersCompanyPermissions($userUuid,$companyuuid,$permissionPlugin);
            $usersPermissions['permission'] = $companyPermission; // replace value
            $usersPermissions['companyPermission'] = $companyPermission;
          }
        } */

        $response = array(
          'token' => $tokenValidity,
          't' => $userData[0]['usersType'],
          'permissions' => $usersPermissions
        );
  
        return (array)$response;


      } else {

        $response = array(
          'token' => $tokenValidity,
          'permissions' => null
        );
  
        return (array)$response;
        
      }

    }
    
}