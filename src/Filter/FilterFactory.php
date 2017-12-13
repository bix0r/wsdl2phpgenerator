<?php
namespace Wsdl2PhpGenerator\Filter;


use Wsdl2PhpGenerator\ConfigInterface;

/**
 * Factory class for retrieving filters.
 */
class FilterFactory
{
    /**
     * Returns a filter matching the provided configuration.
     *
     * @param ConfigInterface $config The configuration to create a filter for.
     * @return FilterInterface[] A matching filter.
     */
    public function create(ConfigInterface $config)
    {
        $filterNames = $config->get('serviceFilters');

		$ret = array();

		foreach ($filterNames as $filterName) {
			$class = 'Wsdl2PhpGenerator\\Filter\\' . $filterName;
			if (!class_exists($class, false)) {
				$filename = __DIR__ . DIRECTORY_SEPARATOR . $filterName . '.php';
				require_once $filename;
			}

			$ret[] = new $class($config);
		}
		return $ret;
    }
}
