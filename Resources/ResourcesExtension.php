<?php

/**
 * This file is part of the Resources extenstion
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\Resources;

use Nette;

if (!class_exists('Nette\DI\CompilerExtension')) {
	class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
	class_alias('Nette\Config\Compiler', 'Nette\DI\Compiler');
	class_alias('Nette\Config\Helpers', 'Nette\DI\Config\Helpers');
}

if (isset(Nette\Loaders\NetteLoader::getInstance()->renamed['Nette\Configurator']) || !class_exists('Nette\Configurator')) {
	unset(Nette\Loaders\NetteLoader::getInstance()->renamed['Nette\Configurator']);
	class_alias('Nette\Config\Configurator', 'Nette\Configurator');
}

/**
 * @author Jáchym Toušek
 */
class ResourcesExtension extends \Nette\DI\CompilerExtension
{

	/** @var array */
	public $defaults = array(
		'inputDirectory' => NULL,
		'cacheDirectory' => NULL,
		'cacheUrl' => NULL,
		'concatenate' => '%productionMode%',
		'cssFilters' => array(),
		'jsFilters' => array(),
		'packages' => array(),
		'mapping' => array(),
	);

	/** @var string[] */
	protected $filters;

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);
		$this->filters = array();

		$builder->addDefinition($this->prefix('loader'))
			->setClass('Arachne\Resources\ResourcesLoader', array($config['packages'], $config['mapping']))
			->addSetup('setConcatenate', array($config['concatenate']));

		$builder->addDefinition($this->prefix('compiler'))
			->setClass('Arachne\Resources\Compiler', array($config['inputDirectory']))
			->addSetup('setCssFilters', array($this->prepareFilterServices($config['cssFilters'])))
			->addSetup('setJsFilters', array($this->prepareFilterServices($config['jsFilters'])));

		$builder->addDefinition($this->prefix('cache'))
			->setClass('Arachne\Resources\PublicCache', array($config['cacheDirectory'], $config['cacheUrl']));

		if ($builder->hasDefinition('nette.latte')) {
			$builder->getDefinition('nette.latte')
				->addSetup('Arachne\Resources\ResourcesMacros::install(?->getCompiler())', array('@self'));
		}
	}

	/**
	 * @param string[] $filters
	 * @return string[]
	 * @throws InvalidStateException
	 */
	protected function prepareFilterServices(array $filters)
	{
		$builder = $this->getContainerBuilder();
		foreach ($filters as &$value) {
			if (\Nette\Utils\Strings::startsWith($value, '@')) {
				$value = substr($value, 1);
			} else {
				$class = $value;
				$value = $this->prefix('filters.' . str_replace('\\', '.', $value));
				$builder->addDefinition($value)
					->setClass($class);
			}
			$this->filters[] = $value;
		}
		return $filters;
	}

	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		foreach ($this->filters as $service) {
			$class = $builder->getDefinition($service)->class ?: $builder->getDefinition($service)->factory->entity;
			if (!in_array('Arachne\Resources\IFilter', class_implements($class))) {
				throw new InvalidStateException("Service '$service' must implement Arachne\Resources\IFilter.");
			}
		}
	}

}
