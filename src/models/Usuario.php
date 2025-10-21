<?php
class Usuario {
    private $conexion;
    private $id;
    private $nombre;
    private $email;
    private $role;

    public function __construct($db) {
        $this->conexion = $db;
    }

    public function login($email, $password) {
        try {
            $stmt = $this->conexion->prepare("SELECT id, nombre, password, role FROM usuarios WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($password, $usuario['password'])) {
                $this->id = $usuario['id'];
                $this->nombre = $usuario['nombre'];
                $_SESSION['role'] = $usuario['role'];
                return true;
            }
            return false;
        } catch(PDOException $e) {
            return false;
        }
    }

    public function registro($nombre, $email, $password, $role) {
        try {
            $hash_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->conexion->prepare("INSERT INTO usuarios (nombre, email, password, role) VALUES (:nombre, :email, :password, :role)");
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hash_password);
            $stmt->bindParam(':role', $role);
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }

    public function obtenerTodos() {
        $stmt = $this->conexion->query("SELECT * FROM usuarios ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function obtenerPorId($id) {
        $stmt = $this->conexion->prepare("SELECT * FROM usuarios WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function actualizar($id, $nombre, $email, $role) {
        try {
            $stmt = $this->conexion->prepare(
                "UPDATE usuarios SET nombre = :nombre, email = :email, role = :role 
                 WHERE id = :id"
            );
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':role', $role);
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function eliminar($id) {
        $stmt = $this->conexion->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Getters
    public function getId() { return $this->id; }
    public function getNombre() { return $this->nombre; }
    public function getEmail() { return $this->email; }
    public function getRole() { return $this->role; }
}
?>