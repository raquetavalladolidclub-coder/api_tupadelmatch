<?php
    class User {
        private $conn;
        private $table_name = "users";

        public $id;
        public $google_id;
        public $email;
        public $name;
        public $avatar;
        public $phone;
        public $level;
        public $created_at;
        public $updated_at;

        public function __construct($db) {
            $this->conn = $db;
        }

        public function create() {
            $query = "INSERT INTO " . $this->table_name . " 
                    SET google_id=:google_id, email=:email, name=:name, 
                        avatar=:avatar, phone=:phone, level=:level, 
                        created_at=:created_at";

            $stmt = $this->conn->prepare($query);

            $this->google_id = htmlspecialchars(strip_tags($this->google_id));
            $this->email = htmlspecialchars(strip_tags($this->email));
            $this->name = htmlspecialchars(strip_tags($this->name));
            $this->avatar = htmlspecialchars(strip_tags($this->avatar));
            $this->phone = htmlspecialchars(strip_tags($this->phone));
            $this->level = htmlspecialchars(strip_tags($this->level));
            $this->created_at = date('Y-m-d H:i:s');

            $stmt->bindParam(":google_id", $this->google_id);
            $stmt->bindParam(":email", $this->email);
            $stmt->bindParam(":name", $this->name);
            $stmt->bindParam(":avatar", $this->avatar);
            $stmt->bindParam(":phone", $this->phone);
            $stmt->bindParam(":level", $this->level);
            $stmt->bindParam(":created_at", $this->created_at);

            if ($stmt->execute()) {
                return true;
            }
            return false;
        }

        public function findByGoogleId($google_id) {
            $query = "SELECT * FROM " . $this->table_name . " WHERE google_id = ? LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $google_id);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->id = $row['id'];
                $this->google_id = $row['google_id'];
                $this->email = $row['email'];
                $this->name = $row['name'];
                $this->avatar = $row['avatar'];
                $this->phone = $row['phone'];
                $this->level = $row['level'];
                $this->created_at = $row['created_at'];
                return true;
            }
            return false;
        }

        public function findByEmail($email) {
            $query = "SELECT * FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->id = $row['id'];
                $this->google_id = $row['google_id'];
                $this->email = $row['email'];
                $this->name = $row['name'];
                $this->avatar = $row['avatar'];
                $this->phone = $row['phone'];
                $this->level = $row['level'];
                $this->created_at = $row['created_at'];
                return true;
            }
            return false;
        }
    }
?>