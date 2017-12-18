<?php

/**
 * @package Wsdl2PhpGenerator
 */
namespace Wsdl2PhpGenerator;

use Wsdl2PhpGenerator\PhpSource\PhpClass;
use Wsdl2PhpGenerator\PhpSource\PhpDocComment;
use Wsdl2PhpGenerator\PhpSource\PhpDocElementFactory;
use Wsdl2PhpGenerator\PhpSource\PhpFunction;
use Wsdl2PhpGenerator\PhpSource\PhpVariable;

/**
 * Service represents the service in the wsdl
 *
 * @package Wsdl2PhpGenerator
 * @author Fredrik Wallgren <fredrik.wallgren@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class Service implements ClassGenerator
{

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var PhpClass The class used to create the service.
     */
    private $class;

    /**
     * @var string The name of the service
     */
    private $identifier;

    /**
     * @var Operation[] An array containing the operations of the service
     */
    private $operations;

    /**
     * @var string The description of the service used as description in the phpdoc of the class
     */
    private $description;

    /**
     * @var Type[] An array of Types
     */
    private $types;

    /**
     * @param ConfigInterface $config Configuration
     * @param string $identifier The name of the service
     * @param Type[] $types The types the service knows about
     * @param string $description The description of the service
     */
    public function __construct(ConfigInterface $config, $identifier, array $types, $description)
    {
        $this->config = $config;
        $this->identifier = $identifier;
        $this->description = $description;
        $this->operations = array();
        $this->types = array();
        foreach ($types as $type) {
            $this->types[$type->getIdentifier()] = $type;
        }
    }

    /**
     * @return PhpClass Returns the class, generates it if not done
     */
    public function getClass()
    {
        if ($this->class == null) {
            $this->generateClass();
        }

        return $this->class;
    }

    /**
     * Returns an operation provided by the service based on its name.
     *
     * @param string $operationName The name of the operation.
     *
     * @return Operation|null The operation or null if it does not exist.
     */
    public function getOperation($operationName)
    {
        return isset($this->operations[$operationName])? $this->operations[$operationName]: null;
    }

    /**
     * Returns the description of the service.
     *
     * @return string The service description.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Returns the identifier for the service ie. the name.
     *
     * @return string The service name.
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Returns a type used by the service based on its name.
     *
     * @param string $identifier The identifier for the type.
     *
     * @return Type|null The type or null if the type does not exist.
     */
    public function getType($identifier)
    {
        return isset($this->types[$identifier])? $this->types[$identifier]: null;
    }
    /**
     * Returns all types defined by the service.
     *
     * @return Type[] An array of types.
     */
    public function getTypes()
    {
        return $this->types;
    }

	public function removeType($identifier)
	{
		if (isset($this->types[$identifier])) {
			unset($this->types[$identifier]);
			return true;
		}
		return false;
	}

    /**
     * Generates the class if not already generated
     */
    public function generateClass()
    {
        $name = $this->identifier;

        // Generate a valid classname
        $name = Validator::validateClass($name, $this->config->get('namespaceName'));

        // uppercase the name
        $name = ucfirst($name);

        // Create the class object
        $comment = new PhpDocComment($this->description);
        $this->class = new PhpClass($name, false, $this->config->get('soapClientClass'), $comment);

		$prepareClass = $this->config->get('servicePrepareClass');
		if (is_callable($prepareClass)) {
			$prepareClass($this);
		}

		$constructor = $this->config->get('serviceConstructor');
		if ($constructor !== false) {
			if (!($constructor instanceof PhpFunction)) {
				// Create the constructor
				$comment = new PhpDocComment();
				$comment->addParam(PhpDocElementFactory::getParam('array', 'options', 'A array of config values'));
				$comment->addParam(PhpDocElementFactory::getParam('string', 'wsdl', 'The wsdl file to use'));

				$source = '
  foreach (self::$classmap as $key => $value) {
    if (!isset($options[\'classmap\'][$key])) {
      $options[\'classmap\'][$key] = $value;
    }
  }' . PHP_EOL;
				$source .= '  $options = array_merge(' . var_export($this->config->get('soapClientOptions'), true) . ', $options);' . PHP_EOL;
				$source .= '  parent::__construct($wsdl, $options);' . PHP_EOL;

				$constructor = new PhpFunction('public', '__construct', 'array $options = array(), $wsdl = \'' . $this->config->get('inputFile') . '\'', $source, $comment);
			}

			// Add the constructor
			$this->class->addFunction($constructor);
		}

        // Generate the classmap
        $name = 'classmap';
        $comment = new PhpDocComment();
        $comment->setVar(PhpDocElementFactory::getVar('array', $name, 'The defined classes'));

        $init = array();
        foreach ($this->types as $typeKey => $type) {
            if ($type instanceof ComplexType) {
                $init[$typeKey] = $this->config->get('namespaceName') . "\\" . $type->getPhpIdentifier();
            }
        }
		$varInitialization = var_export($init, true);
        if ($this->config->get('classmapUse::class')) {
        	$varInitialization = preg_replace("@(\s*=>\s*)'(.*)',@", '\1\2::class,', $varInitialization);
        	$varInitialization = preg_replace('@\\\\+@', "\\", $varInitialization);
        	$ns = $this->config->get('namespaceName') . '\\';
        	$varInitialization = str_replace($ns, '', $varInitialization);
		}
		$access = $this->config->get('classmapAccess');
		$var = new PhpVariable($access . ' static', $name, $varInitialization, $comment);

		$arrayStart = 'array(';
		$arrayEnd = ')';
		if ($this->config->get('useShortArrays')) {
			$arrayStart = '[';
			$arrayEnd = ']';
			$var->setInitialization($this->shortenArray($var->getInitialization()));
		}


        // Add the classmap variable
        $this->class->addVariable($var);

		$soapCall = $this->config->get('soapCallMethod');

        // Add all methods
        foreach ($this->operations as $operation) {
            $name = Validator::validateOperation($operation->getName());

            $comment = new PhpDocComment($operation->getDescription());
            $comment->setReturn(PhpDocElementFactory::getReturn($operation->getReturns(), ''));

            foreach ($operation->getParams() as $param => $hint) {
                $arr = $operation->getPhpDocParams($param, $this->types);
                $comment->addParam(PhpDocElementFactory::getParam(
					Validator::validateType($arr['type']),
					$arr['name'],
					$arr['desc']
				));
            }

            $source = '  return $this->' . $soapCall . '(\'' . $operation->getSoapName() . '\', ' . $arrayStart . $operation->getParamStringNoTypeHints() . $arrayEnd . ');' . PHP_EOL;


            $paramStr = $operation->getParamString($this->types);
			$paramStr = $this->optionalParams($paramStr);

            $function = new PhpFunction('public', $name, $paramStr, $source, $comment);

            if ($this->class->functionExists($function->getIdentifier()) == false) {
                $this->class->addFunction($function);
            }
        }
    }

	private function shortenArray($varStr)
	{
		return preg_replace('/array\s*\(([^)]+)\)/m', '[\1]', $varStr);
	}

    /**
     * Add an operation to the service.
     *
     * @param Operation $operation The operation to be added.
     */
    public function addOperation(Operation $operation)
    {
        $this->operations[$operation->getName()] = $operation;
    }

	/**
	 * @return Operation[]
	 */
	public function getOperations()
	{
		return $this->operations;
	}

	private function optionalParams($paramStr)
	{
		// todo config: should be run
		// todo config: what should be optional
		return preg_replace('/Options \$(opt\w*)\b/', 'Options $\1 = null', $paramStr);
	}
}
