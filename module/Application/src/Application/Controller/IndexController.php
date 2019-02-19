<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');
        return $this->_redirect()->toUrl('/summary/dashboard');
        return new ViewModel();
    }
    
    public function samplesAccessionAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');          
        return new ViewModel();
    }
    
    public function samplesWaitingAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');          
        return new ViewModel();
    }
    
    
    public function samplesRejectedAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');          
        return new ViewModel();
    }
    
    public function samplesTestedAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');
        return new ViewModel();
    }
    
    public function requisitionFormsIncompleteAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');          
        return new ViewModel();
    }
}
