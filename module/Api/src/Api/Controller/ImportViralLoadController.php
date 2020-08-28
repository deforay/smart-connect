<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class ImportViralLoadController extends AbstractRestfulController
{

	public function __construct()
	{
	}

	public function getList()
	{
		exit('Nothing to see here');
	}
	public function create($params)
	{
		$response = array();
		if (isset($_FILES['vlFile']['name']) && trim($_FILES['vlFile']['name']) != "") {
			$pathname = UPLOAD_PATH . DIRECTORY_SEPARATOR . "not-import-vl";
			if (!file_exists($pathname) && !is_dir($pathname)) {
				mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "not-import-vl");
			}
			if (move_uploaded_file($_FILES["vlFile"]["tmp_name"], $pathname . DIRECTORY_SEPARATOR . $_FILES["vlFile"]["name"])) {
				$response['status'] = 'success';
			}
		}
		return new JsonModel($response);
	}
}
