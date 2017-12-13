<?php

namespace Wsdl2PhpGenerator\Filter;

use Wsdl2PhpGenerator\Service;

class VoidFilter implements FilterInterface
{
	public function filter(Service $service)
	{
		foreach ($service->getOperations() as $operation) {
			$returns = $operation->getReturns();
			if ($returns == 'void') {
				$operation->setReturns('null');
			}
		}
		return $service;
	}
}