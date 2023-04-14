<?php

namespace Application\Controller;


use Laminas\Session\Container;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

use Application\Controller\Traits\AjaxActionTrait;
use Application\Controller\Traits\AclAwareTrait;

/**
 * Abstract App Controller
 * Plugin and trait method signatures for static analysis
 * @codingStandardsIgnoreStart
 * @method \Laminas\Http\PhpEnvironment\Request getRequest()
 * @method \Laminas\Http\PhpEnvironment\Response getResponse()
 * @codingStandardsIgnoreEnd
 */


abstract class AbstractAppController extends AbstractActionController
{


    use AjaxActionTrait;
    use AclAwareTrait;

    /** @var Request $request */
    protected $request;
    /** @var Response $response */
    protected $response;
    /** @var string $appPath */
    public $appPath;
    /** @var string $baseUrl */
    public $baseUrl;
    /** @var string $basePath */
    public $basePath;
    /** @var string $referringUrl */
    public $referringUrl;
    /** @var int|string $resourceId */
    protected $resourceId;
    /** @var ViewModel $view */
    protected $view;
    /** @var array<string, mixed> $config */
    protected $config;

    /**
     * @return void
     * @param array<string, mixed> $config
     * */
    public function __construct(array $config = null)
    {
        $this->config   = $config;
        $this->view     = new ViewModel();
        //$this->appPath  = $this->config['app_settings']['server']['app_path'];
        //$this->basePath = $this->appPath;

        $this->view->setVariables([
            'resourceId' => null,
        ]);
    }
}
