<?php

namespace Wsdl2PhpGenerator\Filter;

use Wsdl2PhpGenerator\ComplexType;
use Wsdl2PhpGenerator\Service;
use Wsdl2PhpGenerator\Validator;

class ArrayFilter implements FilterInterface
{
	public function filter(Service $service)
	{
		foreach ($service->getOperations() as $operation) {
			$returns = $operation->getReturns();
			$newReturns = $this->getNewIdentifier($returns);
			if ($returns != $newReturns) {
				$newReturns = $this->validateReturnArray($newReturns);
				$operation->setReturns($newReturns);
			}

			$params = [];
			foreach ($operation->getParams() as $param => $paramType) {
				$newParamType = $this->getNewIdentifier($paramType);
				if ($paramType != $newParamType) {
					$paramType = $this->validateReturnArray($newParamType);
				}
				$params[$param] = $paramType;
			}
			$operation->setParams($params);
		}
		foreach ($service->getTypes() as $type) {
			$identifier = $type->getPhpIdentifier();
			$newIdentifier = $this->getNewIdentifier($identifier);
			if ($identifier != $newIdentifier) {
				$service->removeType($identifier);
			}
			if ($type instanceof ComplexType) {
				foreach ($type->getMembers() as $member) {
					$memberType = $member->getType();
					$newMemberType = $this->getNewIdentifier($memberType);
					if ($memberType != $newMemberType) {
						$newMemberType = $this->validateReturnArray($newMemberType);
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
		if (preg_match_all('/^ArrayOf(.*)/', $identifier, $matches)) {
			$ret = $matches[1][0];
			if (substr($ret, -1) == 's') {
				if (substr($ret, -3) == 'ies') {
					$ret = substr($ret, 0, -3) . 'y';
				}
				else {
					$ret = substr($ret, 0, -1);
				}
			}
		}
		else if (preg_match_all('/^(.*)Array$/', $identifier, $matches)) {
			$ret = $matches[1][0];
		}
		return $ret;
	}

	private function validateReturnArray($return)
	{
		$return = Validator::validateType($return);
		return $return . '[]';
	}

}