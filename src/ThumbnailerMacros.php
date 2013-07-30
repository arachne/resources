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
 * @author Dusan Hudak <admin@dusan-hudak.com>
 */
class ThumbnailerMacros extends \Latte\Macros\MacroSet
{

	/**
	 * @param \Latte\Compiler $compiler
	 */
	public static function install(\Latte\Compiler $compiler)
	{
		$me = new static($compiler);
		$me->addMacro('linkThumbnail', 'echo $_presenter->getContext()->getByType("Arachne\Resources\Thumbnailer")->getLinkThumbnail(%node.array)');
		$me->addMacro('thumbnail', 'echo $_presenter->getContext()->getByType("Arachne\Resources\Thumbnailer")->getThumbnail(%node.array)');
		$me->addMacro('thumbnailUrl', 'echo $_presenter->getContext()->getByType("Arachne\Resources\Thumbnailer")->getThumbnailUrl(%node.array)');
	}

}
