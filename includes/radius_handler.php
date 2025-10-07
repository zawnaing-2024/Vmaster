<?php
require_once __DIR__ . '/../config/radius.php';

class RadiusHandler {
    private $radiusConn;
    
    public function __construct() {
        $this->radiusConn = getRadiusConnection();
    }
    
    /**
     * Create RADIUS user
     */
    public function createUser($username, $password) {
        if (!RADIUS_ENABLED || !$this->radiusConn) {
            return false;
        }
        
        try {
            // Check if user already exists
            $stmt = $this->radiusConn->prepare("SELECT COUNT(*) as count FROM radcheck WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()['count'] > 0) {
                error_log("RADIUS user already exists: $username");
                return false;
            }
            
            // Insert user with password
            $stmt = $this->radiusConn->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
            $stmt->execute([$username, $password]);
            
            error_log("RADIUS user created: $username");
            return true;
        } catch(Exception $e) {
            error_log("RADIUS createUser failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete RADIUS user
     */
    public function deleteUser($username) {
        if (!RADIUS_ENABLED || !$this->radiusConn) {
            return false;
        }
        
        try {
            $stmt = $this->radiusConn->prepare("DELETE FROM radcheck WHERE username = ?");
            $stmt->execute([$username]);
            
            // Also delete from radreply if exists
            $stmt = $this->radiusConn->prepare("DELETE FROM radreply WHERE username = ?");
            $stmt->execute([$username]);
            
            error_log("RADIUS user deleted: $username");
            return true;
        } catch(Exception $e) {
            error_log("RADIUS deleteUser failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Suspend RADIUS user (add reject rule)
     */
    public function suspendUser($username) {
        if (!RADIUS_ENABLED || !$this->radiusConn) {
            return false;
        }
        
        try {
            // Add Auth-Type Reject
            $stmt = $this->radiusConn->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')");
            $stmt->execute([$username]);
            
            error_log("RADIUS user suspended: $username");
            return true;
        } catch(Exception $e) {
            error_log("RADIUS suspendUser failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reactivate RADIUS user (remove reject rule)
     */
    public function reactivateUser($username) {
        if (!RADIUS_ENABLED || !$this->radiusConn) {
            return false;
        }
        
        try {
            // Remove Auth-Type Reject
            $stmt = $this->radiusConn->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Auth-Type'");
            $stmt->execute([$username]);
            
            error_log("RADIUS user reactivated: $username");
            return true;
        } catch(Exception $e) {
            error_log("RADIUS reactivateUser failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user exists
     */
    public function userExists($username) {
        if (!RADIUS_ENABLED || !$this->radiusConn) {
            return false;
        }
        
        try {
            $stmt = $this->radiusConn->prepare("SELECT COUNT(*) as count FROM radcheck WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch()['count'] > 0;
        } catch(Exception $e) {
            error_log("RADIUS userExists failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Change user password
     */
    public function changePassword($username, $newPassword) {
        if (!RADIUS_ENABLED || !$this->radiusConn) {
            return false;
        }
        
        try {
            $stmt = $this->radiusConn->prepare("UPDATE radcheck SET value = ? WHERE username = ? AND attribute = 'Cleartext-Password'");
            $stmt->execute([$newPassword, $username]);
            
            error_log("RADIUS password changed for: $username");
            return true;
        } catch(Exception $e) {
            error_log("RADIUS changePassword failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user status
     */
    public function getUserStatus($username) {
        if (!RADIUS_ENABLED || !$this->radiusConn) {
            return 'unknown';
        }
        
        try {
            // Check for reject rule
            $stmt = $this->radiusConn->prepare("SELECT COUNT(*) as count FROM radcheck WHERE username = ? AND attribute = 'Auth-Type' AND value = 'Reject'");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()['count'] > 0) {
                return 'suspended';
            }
            
            // Check if user exists
            if ($this->userExists($username)) {
                return 'active';
            }
            
            return 'not_found';
        } catch(Exception $e) {
            error_log("RADIUS getUserStatus failed: " . $e->getMessage());
            return 'error';
        }
    }
    
    /**
     * Get all users
     */
    public function getAllUsers() {
        if (!RADIUS_ENABLED || !$this->radiusConn) {
            return [];
        }
        
        try {
            $stmt = $this->radiusConn->query("SELECT DISTINCT username FROM radcheck WHERE attribute = 'Cleartext-Password' ORDER BY username");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch(Exception $e) {
            error_log("RADIUS getAllUsers failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Test RADIUS connection
     */
    public function testConnection() {
        if (!$this->radiusConn) {
            return false;
        }
        
        try {
            $this->radiusConn->query("SELECT 1");
            return true;
        } catch(Exception $e) {
            return false;
        }
    }
}

