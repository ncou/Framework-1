<?php
namespace Wandu\Http\Session\Storage;

use Wandu\Http\Contracts\SessionInterface;
use Wandu\Http\Session\Session;
use Wandu\Http\Session\SessionAdapterInterface;

class FileAdapter implements SessionAdapterInterface
{
    /** @var string */
    private $fileRoot;

    /**
     * @param string $fileRoot
     */
    public function __construct($fileRoot)
    {
        $this->fileRoot = $fileRoot;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        unlink($this->fileRoot . '/' . $sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        $path = $this->fileRoot . '/' . $sessionId;
        return new Session(file_exists($path) ? unserialize(file_get_contents($path)) : []);
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, SessionInterface $session)
    {
        $path = $this->fileRoot . '/' . $sessionId;
        file_put_contents(
            $path,
            serialize($session->toArray())
        );
    }
}
