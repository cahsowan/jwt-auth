<?php

/*
 * This file is part of jwt-auth
 *
 * (c) Sean Tymon <tymon148@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tymon\JWTAuth;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Http\Parser;
use Tymon\JWTAuth\Support\CustomClaims;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Tymon\JWTAuth\Exceptions\JWTException;

class JWT
{
    use CustomClaims;

    /**
     * @var \Tymon\JWTAuth\Manager
     */
    protected $manager;

    /**
     * @var \Tymon\JWTAuth\Http\Parser
     */
    protected $parser;

    /**
     * @var \Tymon\JWTAuth\Token
     */
    protected $token;

    /**
     * @param \Tymon\JWTAuth\Manager                   $manager
     * @param \Tymon\JWTAuth\Http\Parser               $parser
     */
    public function __construct(Manager $manager, Parser $parser)
    {
        $this->manager = $manager;
        $this->parser = $parser;
    }

    /**
     * Generate a token using the user identifier as the subject claim.
     *
     * @param \Tymon\JWTAuth\Contracts\JWTSubject $user
     *
     * @return string
     */
    public function fromUser(JWTSubject $user)
    {
        $payload = $this->makePayload($user);

        return $this->manager->encode($payload)->get();
    }

    /**
     * Refresh an expired token.
     *
     * @return string
     */
    public function refresh()
    {
        $this->requireToken();

        return $this->manager->customClaims($this->getCustomClaims())->refresh($this->token)->get();
    }

    /**
     * Invalidate a token (add it to the blacklist).
     *
     * @param  bool  $forceForever
     *
     * @return bool
     */
    public function invalidate($forceForever = false)
    {
        $this->requireToken();

        return $this->manager->invalidate($this->token, $forceForever);
    }

    /**
     * Alias to get the payload, and as a result checks that
     * the token is valid i.e. not expired or blacklisted
     *
     * @throws JWTException
     *
     * @return \Tymon\JWTAuth\Payload
     */
    public function checkOrFail()
    {
        return $this->getPayload();
    }

    /**
     * Check that the token is valid
     *
     * @return bool
     */
    public function check()
    {
        try {
            $this->checkOrFail();
        } catch (JWTException $e) {
            return false;
        }

        return true;
    }

    /**
     * Get the token.
     *
     * @return false|Token
     */
    public function getToken()
    {
        if (! $this->token) {
            try {
                $this->parseToken();
            } catch (JWTException $e) {
                return false;
            }
        }

        return $this->token;
    }

    /**
     * Parse the token from the request.
     *
     * @throws \Tymon\JWTAuth\Exceptions\JWTException
     *
     * @return JWTAuth
     */
    public function parseToken()
    {
        if (! $token = $this->parser->parseToken()) {
            throw new JWTException('The token could not be parsed from the request');
        }

        return $this->setToken($token);
    }

    /**
     * Get the raw Payload instance.
     *
     * @return \Tymon\JWTAuth\Payload
     */
    public function getPayload()
    {
        $this->requireToken();

        return $this->manager->decode($this->token);
    }

    /**
     * Create a Payload instance.
     *
     * @param \Tymon\JWTAuth\Contracts\JWTSubject $user
     *
     * @return \Tymon\JWTAuth\Payload
     */
    public function makePayload(JWTSubject $user)
    {
        return $this->factory()->customClaims($this->getClaimsArray($user))->make();
    }

    /**
     * Build the claims array and return it
     *
     * @param \Tymon\JWTAuth\Contracts\JWTSubject $user
     *
     * @return array
     */
    protected function getClaimsArray(JWTSubject $user)
    {
        return array_merge(
            ['sub' => $user->getJWTIdentifier()],
            $this->customClaims,
            $user->getJWTCustomClaims()
        );
    }

    /**
     * Set the token.
     *
     * @param  Token|string  $token
     *
     * @return JWTAuth
     */
    public function setToken($token)
    {
        $this->token = $token instanceof Token ? $token : new Token($token);

        return $this;
    }

    /**
     * Unset the current token.
     *
     * @return JWTAuth
     */
    public function unsetToken()
    {
        $this->token = null;

        return $this;
    }

    /**
     * Ensure that a token is available.
     *
     * @throws \Tymon\JWTAuth\Exceptions\JWTException
     */
    protected function requireToken()
    {
        if (! $this->token) {
            throw new JWTException('A token is required');
        }
    }

    /**
     * Set the request instance.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return JWTAuth
     */
    public function setRequest(Request $request)
    {
        $this->parser->setRequest($request);

        return $this;
    }

    /**
     * Get the Manager instance.
     *
     * @return \Tymon\JWTAuth\Manager
     */
    public function manager()
    {
        return $this->manager;
    }

    /**
     * Get the Parser instance
     *
     * @return \Tymon\JWTAuth\Http\Parser
     */
    public function parser()
    {
        return $this->parser;
    }

    /**
     * Get the Payload Factory
     *
     * @return \Tymon\JWTAuth\Factory
     */
    public function factory()
    {
        return $this->manager->getPayloadFactory();
    }

    /**
     * Get the Blacklist
     *
     * @return \Tymon\JWTAuth\Blacklist
     */
    public function blacklist()
    {
        return $this->manager->getBlacklist();
    }

    /**
     * Magically call the JWT Manager.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->manager, $method)) {
            return call_user_func_array([$this->manager, $method], $parameters);
        }

        throw new \BadMethodCallException("Method [$method] does not exist.");
    }
}
