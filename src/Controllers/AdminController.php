<?php
namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\User;
use PadelClub\Utils\JWTUtils;

class AdminController
{
    /**
     * Listar todos los usuarios (solo admin)
     */
    public function listUsers(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        
        // Verificar que el usuario es admin
        $admin = User::find($userId);
        if (!$admin || $admin->role !== 'admin') {
            return $this->errorResponse($response, 'No tienes permisos de administrador', 403);
        }

        try {
            $users = User::select('id', 'username', 'email', 'fullName', 'nombre', 'apellidos', 'phone', 'genero', 'categoria', 'fiabilidad', 'role', 'is_active', 'imagePath', 'created_at')
                        ->orderBy('created_at', 'desc')
                        ->get()
                        ->toArray();

            return $this->successResponse($response, [
                'users' => $users,
                'total' => count($users)
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al obtener usuarios: ' . $e->getMessage());
        }
    }

    /**
     * Obtener un usuario por ID (solo admin)
     */
    public function getUser(Request $request, Response $response, $args)
    {
        $userId = $request->getAttribute('user_id');
        $targetId = $args['id'] ?? null;

        // Verificar que el usuario es admin
        $admin = User::find($userId);
        if (!$admin || $admin->role !== 'admin') {
            return $this->errorResponse($response, 'No tienes permisos de administrador', 403);
        }

        if (!$targetId) {
            return $this->errorResponse($response, 'ID de usuario requerido');
        }

        try {
            $user = User::find($targetId);
            if (!$user) {
                return $this->errorResponse($response, 'Usuario no encontrado', 404);
            }

            return $this->successResponse($response, [
                'user' => [
                    'id'          => $user->id,
                    'username'    => $user->username,
                    'email'       => $user->email,
                    'fullName'   => $user->fullName,
                    'nombre'      => $user->nombre,
                    'apellidos'   => $user->apellidos,
                    'phone'       => $user->phone,
                    'genero'      => $user->genero,
                    'categoria'   => $user->categoria,
                    'fiabilidad'  => $user->fiabilidad,
                    'role'        => $user->role,
                    'is_active'   => $user->is_active,
                    'imagePath'   => $user->imagePath,
                    'ligas'       => $user->codLiga ?? '',
                    'created_at'  => $user->created_at
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al obtener usuario: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar datos de un usuario (solo admin)
     */
    public function updateUser(Request $request, Response $response, $args)
    {
        $userId = $request->getAttribute('user_id');
        $targetId = $args['id'] ?? null;
        $data = $request->getParsedBody();

        // Verificar que el usuario es admin
        $admin = User::find($userId);
        if (!$admin || $admin->role !== 'admin') {
            return $this->errorResponse($response, 'No tienes permisos de administrador', 403);
        }

        if (!$targetId) {
            return $this->errorResponse($response, 'ID de usuario requerido');
        }

        try {
            $user = User::find($targetId);
            if (!$user) {
                return $this->errorResponse($response, 'Usuario no encontrado', 404);
            }

            // Campos permitidos para actualizar por admin
            $allowedFields = [
                'username', 'email', 'fullName', 'nombre', 'apellidos', 
                'phone', 'genero', 'categoria', 'fiabilidad', 'role', 'is_active'
            ];

            $updates = [];
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $user->$field = $value;
                    $updates[] = $field;
                }
            }

            if (!empty($updates)) {
                $user->save();
            }

            // Si se envió nueva contraseña
            if (!empty($data['password'])) {
                $newPassword = $data['password'];
                if (strlen($newPassword) < 6) {
                    return $this->errorResponse($response, 'La contraseña debe tener al menos 6 caracteres');
                }
                $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
                $user->save();
            }

            return $this->successResponse($response, [
                'message' => 'Usuario actualizado correctamente',
                'updated_fields' => $updates,
                'password_changed' => !empty($data['password'])
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al actualizar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Cambiar contraseña de un usuario (solo admin)
     */
    public function changeUserPassword(Request $request, Response $response, $args)
    {
        $userId = $request->getAttribute('user_id');
        $targetId = $args['id'] ?? null;
        $data = $request->getParsedBody();

        // Verificar que el usuario es admin
        $admin = User::find($userId);
        if (!$admin || $admin->role !== 'admin') {
            return $this->errorResponse($response, 'No tienes permisos de administrador', 403);
        }

        if (!$targetId) {
            return $this->errorResponse($response, 'ID de usuario requerido');
        }

        $newPassword = $data['password'] ?? null;
        if (!$newPassword || strlen($newPassword) < 6) {
            return $this->errorResponse($response, 'La contraseña debe tener al menos 6 caracteres');
        }

        try {
            $user = User::find($targetId);
            if (!$user) {
                return $this->errorResponse($response, 'Usuario no encontrado', 404);
            }

            $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
            $user->save();

            return $this->successResponse($response, [
                'message' => 'Contraseña cambiada correctamente'
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al cambiar contraseña: ' . $e->getMessage());
        }
    }

    /**
     * Buscar usuarios (solo admin)
     */
    public function searchUsers(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';

        // Verificar que el usuario es admin
        $admin = User::find($userId);
        if (!$admin || $admin->role !== 'admin') {
            return $this->errorResponse($response, 'No tienes permisos de administrador', 403);
        }

        try {
            $users = User::where('username', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('fullName', 'like', "%{$query}%")
                        ->orWhere('nombre', 'like', "%{$query}%")
                        ->orWhere('apellidos', 'like', "%{$query}%")
                        ->orderBy('created_at', 'desc')
                        ->limit(50)
                        ->get()
                        ->toArray();

            return $this->successResponse($response, [
                'users' => $users,
                'total' => count($users),
                'query' => $query
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al buscar usuarios: ' . $e->getMessage());
        }
    }

    private function successResponse(Response $response, $data, $statusCode = 200)
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));
        
        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
    
    private function errorResponse(Response $response, $message, $statusCode = 400)
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message
        ]));
        
        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}
