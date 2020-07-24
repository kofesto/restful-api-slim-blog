<?php

namespace Marcosricardoss\Restful\Controller;

use Firebase\JWT\JWT;
use Marcosricardoss\Restful\Helpers;
use Marcosricardoss\Restful\Model\User;
use Marcosricardoss\Restful\Model\BlacklistedToken;
use Marcosricardoss\Restful\Security\UserAuthenticator;

final class AuthController {

      /**
     * Login a user.
     *
     * @param Slim\Http\Request  $request
     * @param Slim\Http\Response $response
     *
     * @return Slim\Http\Response
     */
    public function login($request, $response) {
        
        $userData = $request->getParsedBody();
        
        if ($this->validateUserData($userData)) {
            return $response->withJson(['message' => 'Email or Password field not provided.'], 400);
        }                       
        
        $user = UserAuthenticator::authenticate($userData['email'], $userData['password']);

        if (!$user) {
            return $response->withJson(['message' => 'Email or Password not valid.'], 401);
        }
        
        return $response->withJson(['token' => $this->generateToken($user->id)])->withHeader('Access-Control-Allow-Origin', '*');
    }

    /**
     * Generate a token for user with passed Id.
     *
     * @param int $userId
     *
     * @return string
     */
    private function generateToken($userId)
    {
        $appSecret = getenv('APP_SECRET');
        $jwtAlgorithm = getenv('JWT_ALGORITHM');
        $timeIssued = time();
        //$tokenId = base64_encode(mcrypt_create_iv(32));
        $tokenId = base64_encode(random_bytes(32));
        $token = [
            'iat'  => $timeIssued, // Issued at: time when the token was generated
            'jti'  => $tokenId, // Json Token Id: an unique identifier for the token
            'nbf'  => $timeIssued, // Not before time
            'exp'  => $timeIssued + 60 * 60 * 24 * 30, // expires in 30 days
            'data' => [ // Data related to the signer user
                'userId'   => $userId, // userid from the users table
            ],
        ];
        return JWT::encode($token, $appSecret, $jwtAlgorithm);
    }

    
    public function logout($request, $response) {
        $user = $request->getAttribute('user');
        $blacklistedToken = new BlacklistedToken();
        $blacklistedToken->token_jti = $request->getAttribute('token_jti');
        $user->blacklistedTokens()->save($blacklistedToken);
        return $response->withJson(['message' => 'Logout Successful'], 200)->withHeader('Access-Control-Allow-Origin', '*');
    }

     /**
     * Register a user.
     *
     * @param Slim\Http\Request  $request
     * @param Slim\Http\Response $response
     *
     * @return Slim\Http\Response
     */
    public function register($request, $response) {
        
        $userData = $request->getParsedBody();        


        if ($this->validateUserData($userData)) {
            return $response->withJson(['message' => 'Email or Password field not provided.'], 400);
        }
     
        if (User::where('email', $userData['email'])->first()) {
            return $response->withJson(['message' => 'Email already exist.'], 409);
        }
        
        User::firstOrCreate(
                [
                    'firstname'=>$userData['firstname'],
                    'lastname' =>$userData['lastname'],
                    'email' => $userData['email'],
                    'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
                    'role'     => 'member',
                ]);
        
        return $response->withJson(['message' => 'User successfully created.'], 201)->withHeader('Access-Control-Allow-Origin', '*');
    }   

     /**
     * Validating user data.
     *
     * @param array $userData
     *
     * @return bool
     */
    private function validateUserData($userData) {
        return !$userData || !Helpers::keysExistAndNotEmptyString(['email', 'password'], $userData);
    }

}
