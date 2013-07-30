<?php

/**
 * This file is part of the Resources extenstion
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\Resources;

/**
 * @author Jáchym Toušek
 */
class ResourcesExtension extends \Nette\DI\CompilerExtension
{

	/** @var array */
	public $defaults = array(
		'resourcesDirectory' => NULL,
		'cacheDirectory' => NULL,
		'cacheUrl' => NULL,
		'concatenate' => '%productionMode%',
		'cssFilters' => array(),
		'jsFilters' => array(),
		'packages' => array(),
		'mapping' => array(),
		'imagesDirectory' => NULL,
		'maxWidth' => NULL,
		'maxHeight' => NULL,
		'linkAttributes' => array(),
		'imageAttributes' => array(),
		'flags' => NULL,
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
				->setClass('Arachne\Resources\Compiler', array($config['resourcesDirectory']))
				->addSetup('setCssFilters', array($this->prepareFilterServices($config['cssFilters'])))
				->addSetup('setJsFilters', array($this->prepareFilterServices($config['jsFilters'])));

		$builder->addDefinition($this->prefix('cache'))
				->setClass('Arachne\Resources\PublicCache', array($config['cacheDirectory'], $config['cacheUrl']));

		$builder->addDefinition($this->prefix('thumbnailer'))
				->setClass('Arachne\Resources\Thumbnailer', array($config['imagesDirectory']))
				->addSetup('setMaxWidth', array($config['maxWidth']))
				->addSetup('setMaxHeight', array($config['maxHeight']))
				->addSetup('setLinkAttributes', array($config['linkAttributes']))
				->addSetup('setImageAttributes', array($config['imageAttributes']))
				->addSetup('setFlags', array($config['flags']));

		if ($builder->hasDefinition('nette.latte')) {
			$builder->getDefinition('nette.latte')
					->addSetup('Arachne\Resources\ResourcesMacros::install(?->getCompiler())', array('@self'));
			$builder->getDefinition('nette.latte')
					->addSetup('Arachne\Resources\ThumbMacro::install(?->getCompiler())', array('@self'));
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
			$class = $builder->getDefinition($service)->class ? : $builder->getDefinition($service)->factory->entity;
			if (!in_array('Arachne\Resources\IFilter', class_implements($class))) {
				throw new InvalidStateException("Service '$service' must implement Arachne\Resources\IFilter.");
			}
		}
	}

}
