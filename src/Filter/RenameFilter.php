<?php

namespace Wsdl2PhpGenerator\Filter;

use Wsdl2PhpGenerator\ComplexType;
use Wsdl2PhpGenerator\ConfigInterface;
use Wsdl2PhpGenerator\Service;
use Wsdl2PhpGenerator\Validator;

class RenameFilter implements FilterInterface
{
	/**
	 * @var ConfigInterface
	 */
	private $config;

	private $map = array();

	public function __construct(ConfigInterface $config)
	{
		$this->config = $config;
		$options = $config->get('filterOptions');
		if (isset($options['RenameFilter'])) {
			$this->map = $options['RenameFilter'];
		}
	}

	public function filter(Service $service)
	{
		foreach ($service->getOperations() as $operation) {
			$opName = $operation->getName();
			$newOpName = $this->getNewIdentifier($opName);
			if ($opName != $newOpName) {
				$operation->setName($newOpName);
			}
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
		$isArray = false;
		if (substr($ret, -2) == '[]') {
			$isArray = true;
			$ret = substr($ret, 0, -2);
		}
		if (isset($this->map[$ret])) {
			$ret = $this->map[$ret];
			if ($isArray) {
				$ret .= '[]';
			}
			return $ret;
		}
		else {
			return $identifier;
		}
	}

}