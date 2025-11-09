<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;

class ImportViralLoadController extends AbstractRestfulController
{
	use JsonResponseTrait;

	public function __construct() {}

	public function getList()
	{
		exit('Nothing to see here');
	}
	public function create($params)
	{
		$response = [];
		if (isset($_FILES['vlFile']['name']) && trim($_FILES['vlFile']['name']) != "") {
			$fileName = UPLOAD_PATH . DIRECTORY_SEPARATOR . "not-import-vl";
			if (!file_exists($fileName) && !is_dir($fileName)) {
				mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "not-import-vl");
			}
			$sanitizedFileName = basename($_FILES["vlFile"]["name"]);
			if (move_uploaded_file($_FILES["vlFile"]["tmp_name"], $fileName . DIRECTORY_SEPARATOR . $sanitizedFileName)) {
				$response['status'] = 'success';
			}
		}
		return $this->jsonResponse($response);
	}
}
