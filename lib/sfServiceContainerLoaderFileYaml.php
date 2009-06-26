<?php

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * sfServiceContainerLoaderFileXml loads YAML files service definitions.
 *
 * The YAML format does not support anonymous services yet (cf. the XML loader).
 *
 * @package    symfony
 * @subpackage service
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id: sfServiceContainerLoaderFileYaml.php 269 2009-03-26 20:39:16Z fabien $
 */
class sfServiceContainerLoaderFileYaml extends sfServiceContainerLoaderFile
{
  public function doLoad($files)
  {
    if (!is_array($files))
    {
      $files = array($files);
    }

    return $this->parse($this->getFilesAsArray($files));
  }

  protected function parse($data)
  {
    $parameters = array();
    $definitions = array();

    foreach ($data as $file => $content)
    {
      // imports
      list($importedDefinitions, $importedParameters) = $this->parseImports($content, $file);
      $definitions = array_merge($definitions, $importedDefinitions);
      $parameters = array_merge($parameters, $importedParameters);

      // parameters
      if (isset($content['parameters']))
      {
        $parameters = array_merge($parameters, $content['parameters']);
      }

      // services
      $definitions = array_merge($definitions, $this->parseDefinitions($content, $file));
    }

    return array($definitions, $parameters);
  }

  protected function parseImports($content, $file)
  {
    if (!isset($content['imports']))
    {
      return array(array(), array());
    }

    $definitions = array();
    $parameters = array();
    foreach ($content['imports'] as $import)
    {
      list($importedDefinitions, $importedParameters) = $this->parseImport($import, $file);

      $definitions = array_merge($definitions, $importedDefinitions);
      $parameters = array_merge($parameters, $importedParameters);
    }

    return array($definitions, $parameters);
  }

  protected function parseImport($import, $file)
  {
    if (isset($import['class']) && $import['class'] != get_class($this))
    {
      $class = (string) $import['class'];
      $loader = new $class($this->container, $this->paths);
    }
    else
    {
      $loader = $this;
    }

    $importedFile = $this->getAbsolutePath((string) $import['resource'], dirname($file));

    return call_user_func(array($loader, 'doLoad'), $importedFile);
  }

  protected function parseDefinitions($content, $file)
  {
    if (!isset($content['services']))
    {
      return array();
    }

    $definitions = array();
    foreach ($content['services'] as $id => $service)
    {
      $definitions[$id] = $this->parseDefinition($service, $file);
    }

    return $definitions;
  }

  protected function parseDefinition($service, $file)
  {
    $definition = new sfServiceDefinition((string) $service['class']);

    if (isset($service['shared']))
    {
      $definition->setShared($service['shared']);
    }

    if (isset($service['constructor']))
    {
      $definition->setConstructor($service['constructor']);
    }

    if (isset($service['file']))
    {
      $definition->setFile($service['file']);
    }

    if (isset($service['arguments']))
    {
      $definition->setArguments($this->resolveServices($service['arguments']));
    }

    if (isset($service['configurator']))
    {
      if (is_string($service['configurator']))
      {
        $definition->setConfigurator($service['configurator']);
      }
      else
      {
        $definition->setConfigurator(array($this->resolveServices($service['configurator'][0]), $service['configurator'][1]));
      }
    }

    if (isset($service['calls']))
    {
      foreach ($service['calls'] as $call)
      {
        $definition->addMethodCall($call[0], $this->resolveServices($call[1]));
      }
    }

    return $definition;
  }

  protected function getFilesAsArray($files)
  {
    $yamls = array();
    foreach ($files as $file)
    {
      $file = $this->getAbsolutePath($file);

      if (!file_exists($file))
      {
        throw new InvalidArgumentException(sprintf('The service file "%s" does not exist.', $file));
      }

      $yamls[$file] = $this->validate(sfYaml::load($file), $file);
    }

    return $yamls;
  }

  protected function validate($content, $file)
  {
    if (is_null($content))
    {
      return $content;
    }

    if (!is_array($content))
    {
      throw new InvalidArgumentException(sprintf('The service file "%s" is not valid.', $file));
    }

    foreach (array_keys($content) as $key)
    {
      if (!in_array($key, array('imports', 'parameters', 'services')))
      {
        throw new InvalidArgumentException(sprintf('The service file "%s" is not valid ("%s" is not recognized).', $file, $key));
      }
    }

    return $content;
  }

  protected function resolveServices($value)
  {
    if (is_array($value))
    {
      $value = array_map(array($this, 'resolveServices'), $value);
    }
    else if (is_string($value) && 0 === strpos($value, '@'))
    {
      $value = new sfServiceReference(substr($value, 1));
    }

    return $value;
  }
}
