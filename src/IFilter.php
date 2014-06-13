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
interface IFilter
{

	/**
	 * @param string
	 * @param string
	 * @return string
	 */
	public function __invoke($input, $file);

	/**
	 * @return string[]
	 */
	public function getIncludedFiles();

}
