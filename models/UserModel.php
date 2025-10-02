<?php

require_once 'BaseModel.php';

class UserModel extends BaseModel
{
    /**
     * Find user by id (safe: cast to int)
     */
    public function findUserById($id)
    {
        $id = (int)$id;
        $sql = "SELECT id, name, email FROM users WHERE id = ?";
        $stmt = self::$_connection->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    /**
     * Find user by keyword (search name or email) - safe LIKE with bound param
     */
    public function findUser($keyword)
    {
        $kw = '%' . $keyword . '%';
        $sql = "SELECT id, name, email FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 50";
        $stmt = self::$_connection->prepare($sql);
        $stmt->bind_param('ss', $kw, $kw);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $users;
    }

    /**
     * Authentication user
     * Supports legacy md5 passwords: if stored password matches md5($password) we rehash to password_hash
     * Returns user array (without password) or false
     */
    public function auth($userNameOrEmail, $password)
    {
        // Use prepared statement to fetch user record (including stored password column)
        $sql = "SELECT id, name, email, password FROM users WHERE name = ? OR email = ? LIMIT 1";
        $stmt = self::$_connection->prepare($sql);
        $stmt->bind_param('ss', $userNameOrEmail, $userNameOrEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return false;
        }

        $stored = $user['password']; // legacy md5 or password_hash

        // Case 1: stored looks like a bcrypt/argon2 hash (starts with $2y$ or $2a$ or $argon2)
        if ( (strpos($stored, '$2y$') === 0) || (strpos($stored, '$2a$') === 0) || (strpos($stored, '$argon2') === 0) ) {
            if (password_verify($password, $stored)) {
                // Optionally rehash if algorithm changed/cost changed
                if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                    $this->updatePasswordHash($user['id'], password_hash($password, PASSWORD_DEFAULT));
                }
                unset($user['password']);
                return $user;
            } else {
                return false;
            }
        }

        // Case 2: legacy MD5 (32 hex chars) — verify and then upgrade
        if (strlen($stored) === 32) {
            if (md5($password) === $stored) {
                // Rehash and store new hash
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $this->updatePasswordHash($user['id'], $newHash);
                unset($user['password']);
                return $user;
            } else {
                return false;
            }
        }

        // Fallback: if stored is something else, try password_verify anyway
        if (password_verify($password, $stored)) {
            if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                $this->updatePasswordHash($user['id'], password_hash($password, PASSWORD_DEFAULT));
            }
            unset($user['password']);
            return $user;
        }

        return false;
    }

    /**
     * Update stored password hash for given user id (internal helper)
     */
    protected function updatePasswordHash($id, $newHash)
    {
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = self::$_connection->prepare($sql);
        $stmt->bind_param('si', $newHash, $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    /**
     * Delete user by id (safe cast to int)
     */
    public function deleteUserById($id)
    {
        $id = (int)$id;
        $sql = 'DELETE FROM users WHERE id = ?';
        $stmt = self::$_connection->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /**
     * Update user
     * $input expected keys: id, name, [password]
     */
    public function updateUser($input)
    {
        $id = (int)$input['id'];

        // Build statement dynamically but safely
        $fields = [];
        $params = [];
        $types = '';

        if (isset($input['name'])) {
            $fields[] = 'name = ?';
            $params[] = $input['name'];
            $types .= 's';
        }

        if (!empty($input['password'])) {
            // Hash the password
            $fields[] = 'password = ?';
            $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            $types .= 's';
        }

        if (empty($fields)) {
            return false; // nothing to update
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $params[] = $id;
        $types .= 'i';

        $stmt = self::$_connection->prepare($sql);
        // bind params dynamically
        $bind_names[] = $types;
        for ($i=0; $i<count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);

        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /**
     * Insert user
     * $input keys: name, password (plain text)
     */
    public function insertUser($input)
    {
        $name = (string)($input['name'] ?? '');
        $password = (string)($input['password'] ?? '');

        if ($name === '' || $password === '') {
            throw new InvalidArgumentException('Missing fields');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (name, password, created_at) VALUES (?, ?, NOW())";
        $stmt = self::$_connection->prepare($sql);
        $stmt->bind_param('ss', $name, $passwordHash);
        $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();
        return $insertId;
    }

    /**
     * Search users
     * Safe implementation (no multi_query). If keyword present, use prepared LIKE.
     */
    public function getUsers($params = [])
    {
        if (!empty($params['keyword'])) {
            $kw = '%' . $params['keyword'] . '%';
            $sql = "SELECT id, name, email FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 100";
            $stmt = self::$_connection->prepare($sql);
            $stmt->bind_param('ss', $kw, $kw);
            $stmt->execute();
            $result = $stmt->get_result();
            $users = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $sql = "SELECT id, name, email FROM users LIMIT 1000";
            $users = $this->select($sql); // assuming select() is safe for read-only static SQL
        }

        return $users;
    }
}
