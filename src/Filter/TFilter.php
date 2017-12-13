<?php

namespace Wsdl2PhpGenerator\Filter;

use Wsdl2PhpGenerator\ComplexType;
use Wsdl2PhpGenerator\Service;

class TFilter implements FilterInterface
{
	public function filter(Service $service)
	{
		foreach ($service->getOperations() as $operation) {
			$returns = $operation->getReturns();
			$newReturns = $this->getNewIdentifier($returns);
			if ($returns != $newReturns) {
				$operation->setReturns($newReturns);
			}

			$params = [];
			foreach ($operation->getParams() as $param => $paramType) {
				$newParamType = $this->getNewIdentifier($paramType);
				if ($paramType != $newParamType) {
					$paramType = $newParamType;
				}
				$params[$param] = $paramType;
			}
			$operation->setParams($params);
		}

		foreach ($service->getTypes() as $type) {
			$identifier = $type->getPhpIdentifier();
			$newIdentifier = $this->getNewIdentifier($identifier);
			if ($identifier != $newIdentifier) {
				$type->switchIdentifier($newIdentifier);
			}
			if ($type instanceof ComplexType) {
				foreach ($type->getMembers() as $member) {
					$memberType = $member->getType();
					$newMemberType = $this->getNewIdentifier($memberType);
					if ($memberType != $newMemberType) {
						$member->setType($newMemberType);
					}
				}
			}
		}
		return $service;
	}

	private function getNewIdentifier($identifier)
	{
		$ret = $identifier;
		if (preg_match('/^T[A-Z]/', $ret)) {
			$ret = substr($ret, 1);
		}
		return $ret;
	}

}