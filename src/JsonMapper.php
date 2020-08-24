<?php
/**
 * Part of JsonMapper
 *
 * PHP version 5
 *
 * @category Netresearch
 * @package  JsonMapper
 * @author   Christian Weiske <cweiske@cweiske.de>
 * @license  OSL-3.0 http://opensource.org/licenses/osl-3.0
 * @link     http://cweiske.de/
 */

/**
 * Automatically map JSON structures into objects.
 *
 * @category Netresearch
 * @package  JsonMapper
 * @author   Christian Weiske <cweiske@cweiske.de>
 * @license  OSL-3.0 http://opensource.org/licenses/osl-3.0
 * @link     http://cweiske.de/
 */
class JsonMapper
{
    const SCALAR_TYPE_NAMES = ['boolean', 'integer', 'double', 'string', 'array', 'object', 'resource', 'resource (closed)', 'NULL'];
    const SCALAR_TYPE_NAMES_ALIASES = [
        'bool' => 'boolean',
        'int' => 'integer',
        'float' => 'double',
        'null' => 'NULL',
    ];
    const TYPERX_PRIORITIES_DEFAULTS = [
        /// by complexity
        ['/^((.*\\[\\])|array)$/',-100], // move arrays to the front
        // (leave unknown (class names) second)
        ['/^(bool(ean)?|int(eger)?|double|float|string|mixed)(\\[\\])?$/',100], // basic types 3rd

        /// by basic type
        // (leave unknown (class names) first
        ['/^object(\\[\\])?$/',1], // move objects to the front
        ['/^(string)(\\[\\])?$/',2],
        ['/^(double|float)(\\[\\])?$/',3],
        ['/^(int(eger)?)(\\[\\])?$/',4],
        ['/^(bool(ean)?)(\\[\\])?$/',5],
        ['/^mixed(\\[\\])?$/',6], // mixed last

    ];
    /**
     * PSR-3 compatible logger object
     *
     * @link http://www.php-fig.org/psr/psr-3/
     * @var  object
     * @see  setLogger()
     */
    protected $logger;

    /**
     * Throw an exception when JSON data contain a property
     * that is not defined in the PHP class
     *
     * @var boolean
     */
    public $bExceptionOnUndefinedProperty = false;

    /**
     * Throw an exception if the JSON data miss a property
     * that is marked with @required in the PHP class
     *
     * @var boolean
     */
    public $bExceptionOnMissingData = false;

    /**
     * If the types of map() parameters shall be checked.
     *
     * You have to disable it if you're using the json_decode "assoc" parameter.
     *
     *     json_decode($str, false)
     *
     * @var boolean
     */
    public $bEnforceMapType = true;

    /**
     * Throw an exception when an object is expected but the JSON contains
     * a non-object type.
     *
     * @var boolean
     */
    public $bStrictObjectTypeChecking = false;


    /**
     * Throw an exception when a JSON contains a value of type which isn't strictly included in the type hints
     * or annotations. eg. don't convert int json value into a string and the opposite
     *
     * @var boolean
     */
    public $bStrictTypeChecking = false;

    /**
     * @var boolean Specifies whether mapper should try the types of a multitype decleration in the exact declared order.
     *
     * Setting this to true will make `$this->prioritizeTypes()` do nothing, providing more control,
     * however for example, note that order is important, especially when `bStrictTypeChecking` is false, for example
     * json value '["a","b","c"]' against multitype decleration 'int|string[]' will result into mapping the json value
     * as an integer '1' as it is settable when `bStrictTypeChecking` is false.
     *
     */
    public $bStrictMultitypeOrdering = false;

    /**
     * Throw an exception, if null value is found
     * but the type of attribute does not allow nulls.
     *
     * @var bool
     */
    public $bStrictNullTypes = true;

    /**
     * Allow mapping of private and proteted properties.
     *
     * @var boolean
     */
    public $bIgnoreVisibility = false;

    /**
     * Remove attributes that were not passed in JSON,
     * to avoid confusion between them and NULL values.
     *
     * @var boolean
     */
    public $bRemoveUndefinedAttributes = false;

    /**
     * Override class names that JsonMapper uses to create objects.
     * Useful when your setter methods accept abstract classes or interfaces.
     *
     * @var array
     */
    public $classMap = array();

    /**
     * Callback used when an undefined property is found.
     *
     * Works only when $bExceptionOnUndefinedProperty is disabled.
     *
     * Parameters to this function are:
     * 1. Object that is being filled
     * 2. Name of the unknown JSON property
     * 3. JSON value of the property
     *
     * @var callable
     */
    public $undefinedPropertyHandler = null;

    /**
     * Runtime cache for inspected classes. This is particularly effective if
     * mapArray() is called with a large number of objects
     *
     * @var array property inspection result cache
     */
    protected $arInspectedClasses = array();

    /**
     * Method to call on each object after deserialization is done.
     *
     * Is only called if it exists on the object.
     *
     * @var string|null
     */
    public $postMappingMethod = null;

    /**
     * Map data all data in $json into the given $object instance.
     *
     * @param object|array $json   JSON object structure from json_decode()
     * @param object       $object Object to map $json data into
     *
     * @return mixed Mapped object is returned.
     * @see    mapArray()
     */
    public function map($json, $object)
    {
        if ($this->bEnforceMapType && !is_object($json)) {
            throw new InvalidArgumentException(
                'JsonMapper::map() requires first argument to be an object'
                . ', ' . gettype($json) . ' given.'
            );
        }
        if (!is_object($object)) {
            throw new InvalidArgumentException(
                'JsonMapper::map() requires second argument to be an object'
                . ', ' . gettype($object) . ' given.'
            );
        }

        $strClassName = get_class($object);
        $rc = new ReflectionClass($object);
        $strNs = $rc->getNamespaceName();
        $providedProperties = array();
        foreach ($json as $key => $jvalue_copy) {
            $jvalue = $jvalue_copy;
            $key = $this->getSafeName($key);
            $providedProperties[$key] = true;

            // Store the property inspection results so we don't have to do it
            // again for subsequent objects of the same type
            if (!isset($this->arInspectedClasses[$strClassName][$key])) {
                $this->arInspectedClasses[$strClassName][$key]
                    = $this->inspectProperty($rc, $key);
            }

            list($hasProperty, $accessor, $full_type, $isNullable)
                = $this->arInspectedClasses[$strClassName][$key];

            // Support for or-ed type declarations in annotations e.g. `type1|type2|type3`
            $types = $this->splitTypeDeclerations($full_type);
            for ($type_index = 0; $type_index < count($types); $type_index++) {
                $type = $types[$type_index];
                $the_last_try = $type_index === count($types) - 1;
                $the_only_try = 1 === count($types);


                if (!$hasProperty) {
                    if ($this->bExceptionOnUndefinedProperty) {
                        throw new JsonMapper_Exception(
                            'JSON property "' . $key . '" does not exist'
                            . ' in object of type ' . $strClassName
                        );
                    } elseif ($this->undefinedPropertyHandler !== null) {
                        call_user_func(
                            $this->undefinedPropertyHandler,
                            $object,
                            $key,
                            $jvalue
                        );
                    } else {
                        $this->log(
                            'info',
                            'Property {property} does not exist in {class}',
                            array('property' => $key, 'class' => $strClassName)
                        );
                    }
                    continue 2;
                }

                if ($accessor === null) {
                    if ($this->bExceptionOnUndefinedProperty) {
                        throw new JsonMapper_Exception(
                            'JSON property "' . $key . '" has no public setter method'
                            . ' in object of type ' . $strClassName
                        );
                    }
                    $this->log(
                        'info',
                        'Property {property} has no public setter method in {class}',
                        array('property' => $key, 'class' => $strClassName)
                    );
                    continue 2;
                }

                if ($isNullable || !$this->bStrictNullTypes) {
                    if ($jvalue === null) {
                        $this->setProperty($object, $accessor, null);
                        continue 2;
                    }
                } elseif ($jvalue === null) {
                    throw new JsonMapper_Exception(
                        'JSON property "' . $key . '" in class "'
                        . $strClassName . '" must not be NULL'
                    );
                }
                try {
                    $this->validateTypeConversion($type, $jvalue, $key, $strClassName);

                    $type = $this->getFullNamespace($type, $strNs);
                    $type = $this->getMappedType($type, $jvalue);

                    if ($type === null || $type === 'mixed') {
                        //no given type - simply set the json data
                        $this->setProperty($object, $accessor, $jvalue);
                        continue 2;
                    } elseif ($this->isObjectOfSameType($type, $jvalue)) {
                        $this->setProperty($object, $accessor, $jvalue);
                        continue 2;
                    } elseif ($this->isSimpleType($type)) {
                        if ($type === 'string' && is_object($jvalue)) {
                            throw new JsonMapper_Exception(
                                'JSON property "' . $key . '" in class "'
                                . $strClassName . '" is an object and'
                                . ' cannot be converted to a string'
                            );
                        } elseif ($type === 'string' && is_array($jvalue)) {
                            throw new JsonMapper_Exception(
                                'JSON property "' . $key . '" in class "'
                                . $strClassName . '" is an array and'
                                . ' cannot be converted to a string'
                            );
                        }
                        settype($jvalue, $type);
                        $this->setProperty($object, $accessor, $jvalue);
                        continue 2;
                    }

                    //FIXME: check if type exists, give detailed error message if not
                    if ($type === '') {
                        throw new JsonMapper_Exception(
                            'Empty type at property "'
                            . $strClassName . '::$' . $key . '"'
                        );
                    }

                    $array = null;
                    $subtype = null;
                    if ($this->isArrayOfType($type)) {
                        //array
                        $array = array();
                        $subtype = substr($type, 0, -2);
                    } elseif (substr($type, -1) == ']') {
                        list($proptype, $subtype) = explode('[', substr($type, 0, -1));
                        if ($proptype == 'array') {
                            $array = array();
                        } else {
                            $array = $this->createInstance($proptype, false, $jvalue);
                        }
                    } else {
                        if (is_a($type, 'ArrayObject', true)) {
                            $array = $this->createInstance($type, false, $jvalue);
                        }
                    }

                    if ($array !== null) {
                        if (!is_array($jvalue) && $this->isFlatType(gettype($jvalue))) {
                            throw new JsonMapper_Exception(
                                'JSON property "' . $key . '" must be an array, '
                                . gettype($jvalue) . ' given'
                            );
                        }

                        $cleanSubtype = $this->removeNullable($subtype);
                        $subtype = $this->getFullNamespace($cleanSubtype, $strNs);
                        $child = $this->mapArray($jvalue, $array, $subtype, $key);
                    } elseif ($this->isFlatType(gettype($jvalue))) {
                        //use constructor parameter if we have a class
                        // but only a flat type (i.e. string, int)
                        if ($this->bStrictObjectTypeChecking) {
                            throw new JsonMapper_Exception(
                                'JSON property "' . $key . '" must be an object, '
                                . gettype($jvalue) . ' given'
                            );
                        }
                        $child = $this->createInstance($type, true, $jvalue);
                    } else {
                        $child = $this->createInstance($type, false, $jvalue);
                        $this->map($jvalue, $child);
                    }
                    $this->setProperty($object, $accessor, $child);
                    continue 2;
                } catch (JsonMapper_Exception $err) {
                    if ($the_only_try) {
                        throw $err;
                    }
                    if ($the_last_try) {
                        throw new JsonMapper_Exception('JSON property "' . $key . '" in class "' . $strClassName . '" is of type "' . gettype($json) .
                            '" and cannot be converted to a value of type "' . strval($full_type) . '"', $err->getCode(), $err);
                    }
                    continue;
                }
            }
        }

        if ($this->bExceptionOnMissingData) {
            $this->checkMissingData($providedProperties, $rc);
        }

        if ($this->bRemoveUndefinedAttributes) {
            $this->removeUndefinedAttributes($object, $providedProperties);
        }

        if ($this->postMappingMethod !== null
            && $rc->hasMethod($this->postMappingMethod)
        ) {
            $refDeserializePostMethod = $rc->getMethod(
                $this->postMappingMethod
            );
            $refDeserializePostMethod->setAccessible(true);
            $refDeserializePostMethod->invoke($object);
        }

        return $object;
    }

    /**
     * Convert a type name to a fully namespaced type name.
     *
     * @param string $type  Type name (simple type or class name)
     * @param string $strNs Base namespace that gets prepended to the type name
     *
     * @return string Fully-qualified type name with namespace
     */
    protected function getFullNamespace($type, $strNs)
    {
        if ($type === null || $type === '' || $type[0] === '\\' || $strNs === '') {
            return $type;
        }
        list($first) = explode('[', $type, 2);
        if ($first === 'mixed' || $this->isSimpleType($first)) {
            return $type;
        }

        //create a full qualified namespace
        return '\\' . $strNs . '\\' . $type;
    }

    /**
     * Check required properties exist in json
     *
     * @param array  $providedProperties array with json properties
     * @param object $rc                 Reflection class to check
     *
     * @throws JsonMapper_Exception
     *
     * @return void
     */
    protected function checkMissingData($providedProperties, ReflectionClass $rc)
    {
        foreach ($rc->getProperties() as $property) {
            $rprop = $rc->getProperty($property->name);
            $docblock = $rprop->getDocComment();
            $annotations = static::parseAnnotations($docblock);
            if (isset($annotations['required'])
                && !isset($providedProperties[$property->name])
            ) {
                throw new JsonMapper_Exception(
                    'Required property "' . $property->name . '" of class '
                    . $rc->getName()
                    . ' is missing in JSON data'
                );
            }
        }
    }

    /**
     * Remove attributes from object that were not passed in JSON data.
     *
     * This is to avoid confusion between those that were actually passed
     * as NULL, and those that weren't provided at all.
     *
     * @param object $object             Object to remove properties from
     * @param array  $providedProperties Array with JSON properties
     *
     * @return void
     */
    protected function removeUndefinedAttributes($object, $providedProperties)
    {
        foreach (get_object_vars($object) as $propertyName => $dummy) {
            if (!isset($providedProperties[$propertyName])) {
                unset($object->{$propertyName});
            }
        }
    }

    /**
     * Map an array
     *
     * @param array  $json       JSON array structure from json_decode()
     * @param mixed  $array      Array or ArrayObject that gets filled with
     *                           data from $json
     * @param string $class      Class name for children objects.
     *                           All children will get mapped onto this type.
     *                           Supports class names and simple types
     *                           like "string" and nullability "string|null".
     *                           Pass "null" to not convert any values
     * @param string $parent_key Defines the key this array belongs to
     *                           in order to aid debugging.
     *
     * @return mixed Mapped $array is returned
     */
    public function mapArray($json, $array, $class = null, $parent_key = '')
    {
        $originalClass = $class;
        foreach ($json as $key => $jvalue) {
            $class = $this->getMappedType($originalClass, $jvalue);
            if ($class === null) {
                $array[$key] = $jvalue;
            } elseif ($this->isArrayOfType($class)) {
                $array[$key] = $this->mapArray(
                    $jvalue,
                    array(),
                    substr($class, 0, -2)
                );
            } elseif ($this->isFlatType(gettype($jvalue))) {
                //use constructor parameter if we have a class
                // but only a flat type (i.e. string, int)
                if ($jvalue === null) {
                    $array[$key] = null;
                } else {
                    if ($this->isSimpleType($class)) {
                        settype($jvalue, $class);
                        $array[$key] = $jvalue;
                    } else {
                        $array[$key] = $this->createInstance(
                            $class,
                            true,
                            $jvalue
                        );
                    }
                }
            } elseif ($this->isFlatType($class)) {
                throw new JsonMapper_Exception(
                    'JSON property "' . ($parent_key ? $parent_key : '?') . '"'
                    . ' is an array of type "' . $class . '"'
                    . ' but contained a value of type'
                    . ' "' . gettype($jvalue) . '"'
                );
            } elseif (is_a($class, 'ArrayObject', true)) {
                $array[$key] = $this->mapArray(
                    $jvalue,
                    $this->createInstance($class)
                );
            } else {
                $array[$key] = $this->map(
                    $jvalue,
                    $this->createInstance($class, false, $jvalue)
                );
            }
        }
        return $array;
    }

    /**
     * Try to find out if a property exists in a given class.
     * Checks property first, falls back to setter method.
     *
     * @param ReflectionClass $rc   Reflection class to check
     * @param string          $name Property name
     *
     * @return array First value: if the property exists
     *               Second value: the accessor to use (
     *                 ReflectionMethod or ReflectionProperty, or null)
     *               Third value: type of the property
     *               Fourth value: if the property is nullable
     */
    protected function inspectProperty(ReflectionClass $rc, $name)
    {
        //try setter method first
        $setter = 'set' . $this->getCamelCaseName($name);

        if ($rc->hasMethod($setter)) {
            $rmeth = $rc->getMethod($setter);
            if ($rmeth->isPublic() || $this->bIgnoreVisibility) {
                $isNullable = false;
                $rparams = $rmeth->getParameters();
                if (count($rparams) > 0) {
                    $pclass = $rparams[0]->getClass();
                    $isNullable = $rparams[0]->allowsNull();
                    if ($pclass !== null) {
                        return array(
                            true, $rmeth,
                            '\\' . $pclass->getName(),
                            $isNullable,
                        );
                    }
                }

                $docblock    = $rmeth->getDocComment();
                $annotations = static::parseAnnotations($docblock);

                if (!isset($annotations['param'][0])) {
                    // If there is no annotations (higher priority) inspect
                    // if there's a scalar type being defined
                    if (PHP_MAJOR_VERSION >= 7) {
                        $ptype = $rparams[0]->getType();
                        if (is_string($ptype)) {
                            return array(true, $rmeth, $ptype, $isNullable);
                        }
                        if (PHP_VERSION >= 7.1
                            && $ptype instanceof ReflectionNamedType
                        ) {
                            return array(
                                true,
                                $rmeth,
                                $ptype->getName(),
                                $ptype->allowsNull()
                            );
                        }

                        return array(true, $rmeth, null, $isNullable);
                    }
                    return array(true, $rmeth, null, $isNullable);
                }
                list($type) = explode(' ', trim($annotations['param'][0]));
                return array(true, $rmeth, $type, $this->isNullable($type));
            }
        }

        //now try to set the property directly
        //we have to look it up in the class hierarchy
        $class = $rc;
        $rprop = null;
        do {
            if ($class->hasProperty($name)) {
                $rprop = $class->getProperty($name);
            }
        } while ($rprop === null && $class = $class->getParentClass());

        if ($rprop === null) {
            //case-insensitive property matching
            foreach ($rc->getProperties() as $p) {
                if ((strcasecmp($p->name, $name) === 0)) {
                    $rprop = $p;
                    break;
                }
            }
        }
        if ($rprop !== null) {
            if ($rprop->isPublic() || $this->bIgnoreVisibility) {
                $docblock    = $rprop->getDocComment();
                $annotations = static::parseAnnotations($docblock);

                if (!isset($annotations['var'][0])) {
                    // If there is no annotations (higher priority) inspect
                    // if there's a scalar type being defined
                    if (PHP_VERSION_ID >= 70400 && $rprop->hasType()) {
                        $rPropType = $rprop->getType();
                        $propTypeName = $rPropType->getName();

                        if ($this->isSimpleType($propTypeName)) {
                            return array(
                              true,
                              $rprop,
                              $propTypeName,
                              $rPropType->allowsNull()
                            );
                        }

                        return array(
                          true,
                          $rprop,
                          '\\'.$propTypeName,
                          $rPropType->allowsNull()
                        );
                    }

                    return array(true, $rprop, null, false);
                }

                //support "@var type description"
                list($type) = explode(' ', $annotations['var'][0]);

                return array(true, $rprop, $type, $this->isNullable($type));
            } else {
                //no setter, private property
                return array(true, null, null, false);
            }
        }

        //no setter, no property
        return array(false, null, null, false);
    }

    /**
     * Removes - and _ and makes the next letter uppercase
     *
     * @param string $name Property name
     *
     * @return string CamelCasedVariableName
     */
    protected function getCamelCaseName($name)
    {
        return str_replace(
            ' ',
            '',
            ucwords(str_replace(array('_', '-'), ' ', $name))
        );
    }

    /**
     * Since hyphens cannot be used in variables we have to uppercase them.
     *
     * Technically you may use them, but they are awkward to access.
     *
     * @param string $name Property name
     *
     * @return string Name without hyphen
     */
    protected function getSafeName($name)
    {
        if (strpos($name, '-') !== false) {
            $name = $this->getCamelCaseName($name);
        }

        return $name;
    }

    /**
     * Set a property on a given object to a given value.
     *
     * Checks if the setter or the property are public are made before
     * calling this method.
     *
     * @param object $object   Object to set property on
     * @param object $accessor ReflectionMethod or ReflectionProperty
     * @param mixed  $value    Value of property
     *
     * @return void
     */
    protected function setProperty(
        $object,
        $accessor,
        $value
    ) {
        if (!$accessor->isPublic() && $this->bIgnoreVisibility) {
            $accessor->setAccessible(true);
        }
        if ($accessor instanceof ReflectionProperty) {
            $accessor->setValue($object, $value);
        } else {
            //setter method
            $accessor->invoke($object, $value);
        }
    }

    /**
     * Create a new object of the given type.
     *
     * This method exists to be overwritten in child classes,
     * so you can do dependency injection or so.
     *
     * @param string  $class        Class name to instantiate
     * @param boolean $useParameter Pass $parameter to the constructor or not
     * @param mixed   $jvalue       Constructor parameter (the json value)
     *
     * @return object Freshly created object
     */
    protected function createInstance(
        $class,
        $useParameter = false,
        $jvalue = null
    ) {
        if ($useParameter) {
            return new $class($jvalue);
        } else {
            $reflectClass = new ReflectionClass($class);
            $constructor  = $reflectClass->getConstructor();
            if (null === $constructor
                || $constructor->getNumberOfRequiredParameters() > 0
            ) {
                return $reflectClass->newInstanceWithoutConstructor();
            }
            return $reflectClass->newInstance();
        }
    }

    /**
     * Get the mapped class/type name for this class.
     * Returns the incoming classname if not mapped.
     *
     * @param string $type   Type name to map
     * @param mixed  $jvalue Constructor parameter (the json value)
     *
     * @return string The mapped type/class name
     */
    protected function getMappedType($type, $jvalue = null)
    {
        if (isset($this->classMap[$type])) {
            $target = $this->classMap[$type];
        } elseif (is_string($type) && $type !== '' && $type[0] == '\\'
            && isset($this->classMap[substr($type, 1)])
        ) {
            $target = $this->classMap[substr($type, 1)];
        } else {
            $target = null;
        }

        if ($target) {
            if (is_callable($target)) {
                $type = $target($type, $jvalue);
            } else {
                $type = $target;
            }
        }
        return $type;
    }

    /**
     * Checks if the given type is a "simple type"
     *
     * @param string $type type name from gettype()
     *
     * @return boolean True if it is a simple PHP type
     *
     * @see isFlatType()
     */
    protected function isSimpleType($type)
    {
        return $type == 'string'
            || $type == 'boolean' || $type == 'bool'
            || $type == 'integer' || $type == 'int'
            || $type == 'double' || $type == 'float'
            || $type == 'array' || $type == 'object';
    }

    /**
     * Checks if the object is of this type or has this type as one of its parents
     *
     * @param string $type  class name of type being required
     * @param mixed  $value Some PHP value to be tested
     *
     * @return boolean True if $object has type of $type
     */
    protected function isObjectOfSameType($type, $value)
    {
        if (false === is_object($value)) {
            return false;
        }

        return is_a($value, $type);
    }

    /**
     * Checks if the given type is a type that is not nested
     * (simple type except array and object)
     *
     * @param string $type type name from gettype()
     *
     * @return boolean True if it is a non-nested PHP type
     *
     * @see isSimpleType()
     */
    protected function isFlatType($type)
    {
        return $type == 'NULL'
            || $type == 'string'
            || $type == 'boolean' || $type == 'bool'
            || $type == 'integer' || $type == 'int'
            || $type == 'double' || $type == 'float';
    }

    /**
     * Returns true if type is an array of elements
     * (bracket notation)
     *
     * @param string $strType type to be matched
     *
     * @return bool
     */
    protected function isArrayOfType($strType)
    {
        return substr($strType, -2) === '[]';
    }

    /**
     * Checks if the given type is nullable
     *
     * @param string $type type name from the phpdoc param
     *
     * @return boolean True if it is nullable
     */
    protected function isNullable($type)
    {
        return stripos('|' . $type . '|', '|null|') !== false;
    }

    /**
     * Remove the 'null' section of a type
     *
     * @param string $type type name from the phpdoc param
     *
     * @return string The new type value
     */
    protected function removeNullable($type)
    {
        if ($type === null) {
            return null;
        }
        return substr(
            str_ireplace('|null|', '|', '|' . $type . '|'),
            1,
            -1
        );
    }

    /**
     * Copied from PHPUnit 3.7.29, Util/Test.php
     *
     * @param string $docblock Full method docblock
     *
     * @return array
     */
    protected static function parseAnnotations($docblock)
    {
        $annotations = array();
        // Strip away the docblock header and footer
        // to ease parsing of one line annotations
        $docblock = substr($docblock, 3, -2);

        $re = '/@(?P<name>[A-Za-z_-]+)(?:[ \t]+(?P<value>.*?))?[ \t]*\r?$/m';
        if (preg_match_all($re, $docblock, $matches)) {
            $numMatches = count($matches[0]);

            for ($i = 0; $i < $numMatches; ++$i) {
                $annotations[$matches['name'][$i]][] = $matches['value'][$i];
            }
        }

        return $annotations;
    }


    /**
     * Splits type declerations e.g. 'type1|type2|type3' from annotated type declarations
     *
     * @return mixed[]
     */
    protected function splitTypeDeclerations($type): array
    {
        if (is_string($type)) {
            /* @var string */
            $type;

            return $this->prioritizeTypes(array_filter(explode('|', $type), function ($value) {
                return strcasecmp('null', $value)!==0;
            }));
        }

        return [$type];
    }

    /**
     * @throws JsonMapper_Exception
     */
    protected function validateTypeConversion(string $annotated_type = null, $json_value, string $key, string $strClassName)
    {
        if ($this->bStrictTypeChecking) {
            if (is_null($annotated_type)) {
                $annotated_type='mixed';
            }
            $type_names = $this->normalizeToSimpleTypes([$annotated_type]);
            $json_type_name = $this->getTypeFromJsonValue($json_value);

            if (!in_array($json_type_name, $type_names, true)) {
                throw new JsonMapper_Exception(
                    'JSON property "' . $key . '" of type "' . $json_type_name . '" in class "'
                        . $strClassName . ' should not be converted to a value of type '.$annotated_type
                );
            }
        }
    }

    /**
     * Normalizes an array of type name annotations to contain only internal types like the ones returned from
     * gettype().
     */
    protected function normalizeToSimpleTypes(array $type_names): array
    {
        if ($this->bStrictTypeChecking) {
            return $this->prioritizeTypes(array_reduce($type_names, function ($carry, $item) {
                if (!empty(static::SCALAR_TYPE_NAMES_ALIASES[$item])) {
                    $carry[] = static::SCALAR_TYPE_NAMES_ALIASES[$item];
                } elseif (in_array($item, static::SCALAR_TYPE_NAMES)) {
                    $carry[] = $item;
                } elseif ($item == 'mixed') {
                    $carry = array_merge($carry, static::SCALAR_TYPE_NAMES);
                } elseif (strpos($item, '[]')!==false) {
                    $carry[] = 'array';
                } else {
                    $carry[] = 'object';
                }

                return $carry;
            }, []));
            $type_names=$this->prioritizeTypes($type_names);
        }
        return $type_names;
    }

    /**
     * Prioritizes types by sorting them using comparator returned from $this->makeTypeNameComparator().
     * @return array
     */
    protected function prioritizeTypes(array $type_names)
    {
        if ($this->bStrictMultitypeOrdering || count($type_names)<=1) {
            return $type_names;
        }
        usort($type_names, $this->makeTypeNameComparator());
        return $type_names;
    }

    /**
     * Returns a comparator function to use against type names.
     *
     * By default a simple comparator is returned which compares first according to a cost-function which scores the
     * typenames based on the matches and scores declared at self::TYPERX_PRIORITIES_DEFAULTS and then if equal with
     * strcasecmp()
     *
     * @return Closure
     */
    protected function makeTypeNameComparator()
    {
        $costf = function ($type_name) {
            static $cost_cache = [];
            if (isset($cost_cache[$type_name])) {
                return $cost_cache[$type_name];
            }
            $total = 0;
            foreach (static::TYPERX_PRIORITIES_DEFAULTS as $priority_entry) {
                $rx = $priority_entry[0];
                if (preg_match($rx, $type_name)) {
                    $total+=$priority_entry[1]??0;
                }
            }
            $cost_cache[$type_name]=$total;
            return $cost_cache[$type_name] = $total;
        };
        return function ($a, $b) use ($costf) {
            $score_a = $costf($a);
            $score_b = $costf($b);
            if ($score_a==$score_b) {
                return strcasecmp($a, $b);
            } elseif ($score_a < $score_b) {
                return -1;
            }
            return 1;
        };
    }

    /**
     * Gets the type of a value (product of json_decode)
     *
     * @param mixed $value
     */
    protected function getTypeFromJsonValue($value): string
    {
        return gettype($value);
    }


    /**
     * Log a message to the $logger object
     *
     * @param string $level   Logging level
     * @param string $message Text to log
     * @param array  $context Additional information
     *
     * @return null
     */
    protected function log($level, $message, array $context = array())
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger PSR-3 compatible logger object
     *
     * @return null
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }
}
