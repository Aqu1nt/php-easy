<?php
namespace Framework\Services;

use Framework\Core\App;
use Framework\Di\LifecycleSingleton;

class History implements LifecycleSingleton
{
    /**
     * @var string
     */
    private static $LAST_URL = "framework-last-url";

    /**
     * The session service
     * @var Session
     */
    private $session;

    /**
     * History constructor.
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Redirects the user to the last known URL
     * @param array $params
     */
    public function back($params = [])
    {
        $url = $this->session->get(self::$LAST_URL);
        App::redirect($url, $params);
    }

    /**
     * Stores the last url
     */
    public function destroy()
    {
        $this->session->set(self::$LAST_URL, App::location());
    }
}