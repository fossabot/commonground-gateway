<?php

namespace App\Service;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use DateTime;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * The data service aims at providing an acces layer to request, session and user information tha can be accesed and changed from differend contexts e.g. actionHandels, Events etc
 */
class DataService
{
    private Environment $twig;

    public function __construct(
        Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * This function hydrates an array with the values of another array bassed on a mapping diffined in dot notation, with al little help from https://github.com/adbario/php-dot-notation and twig
     *
     * @param array $source      the array that contains the data that is mapped
     * @param array $mapping     the array that determines how the mapping takes place
     * @param bool $list         whether the mappable objects are contained in a list (isnstead of a single object)
     *
     * @return array
     */
    public function mapper(array $source, $mapping, bool $list = false): array
    {
        // Lets first check if we are dealing with a list
        if($list){
            // We need to map object for object so....
            foreach($list as $key => $value){
                $list[$key] = $this->mapper($source, $mapping);
            }
            return $list;
        }

        // We are using dot notation for array's so lets make sure we do not intefene on the . part
        $destination = $this->encodeArrayKeys($source, '.', '&#2E');

        // lets get any drops
        $drops = [];
        if(array_key_exists('_drop',$mapping)){
            $drops = $mapping['_drops'];
            unset($mapping['_drops']);
        }

        // Lets turn  destination into a dat array
        $destination = new \Adbar\Dot($destination);

        // Lets use the mapping to hydrate the array
        foreach ($mapping as $key => $value) {
            // lets handle non-twig mapping
            if($destination->has($value)){
                $destination[$key] =$destination->get($value);
            }
            // and then the twig mapping
            $destination[$key] = castValue($this->twig->createTemplate($value)->render(['source'=>$source]));
        }

        // Lets remove the drops (is anny
        foreach ($drops as $drop){
            if($destination->has($drop)){
                $destination->clear($drop);
            }
            else{
                // @todo throw error?
            }
        }

        // Let turn the dot array back into an array
        $destination = $destination->all();
        $destination = $this->encodeArrayKeys($destination, '&#2E', '.');

        return $destination;
    }

    /**
     * This function cast a value to a specific value type
     *
     * @param string $value
     * @return void
     */
    public function castValue(string $value)
    {
        // Find the format for this value
        // @todo this should be a regex
        if (strpos($value, '|')) {
            $values = explode('|', $value);
            $value = trim($values[0]);
            $format = trim($values[1]);
        }
        else{
            return $value;
        }

        // What if....
        if(!isset($format)){
            return $value;
        }

        // Lets cast
        switch ($format){
            case 'string':
                return  strval($value);
            case 'bool':
            case 'boolean':
                return  boolval($value);
            case 'int':
            case 'integer':
                return  intval($value);
            case 'float':
                return  floatval($value);
            case 'array':
                return  (array) $value;
            case 'date':
                return  new DateTime($value);
            case 'url':
                return  urlencode($value);
            case 'rawurl':
                return  rawurlencode($value);
            case 'base64':
                return  base64_encode($value);
            case 'json':
                return  json_encode($value);
            case 'xml':
                $xmlEncoder = new XmlEncoder();
                return  $xmlEncoder->decode($value, 'xml');
            case 'mapping':
                // @todo
            default:
                //@todo throw error
        }
    }

    private function encodeArrayKeys($array, string $toReplace, string $replacement): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = str_replace($toReplace, $replacement, $key);

            if (\is_array($value) && $value) {
                $result[$newKey] = $this->encodeArrayKeys($value, $toReplace, $replacement);
                continue;
            }
            $result[$newKey] = $value;

            if ($value === [] && $newKey != 'results') {
                unset($result[$newKey]);
            }
        }

        return $result;
    }

}
