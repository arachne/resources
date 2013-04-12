<?php

namespace Arachne\Resources;

use Nette\Latte\Macros\MacroSet;

/**
 * ThumbMacro extenstion.
 *
 * @version    0.1
 * @package    Resources extenstion
 * 
 * @author Dusan Hudak <admin@dusan-hudak.com>
 * @copyright  Copyright (c) 2013 Dusan Hudak
 */
class ThumbMacro extends MacroSet {

    /**
     * @param Nette\Latte\Compiler $compiler
     */
    public static function install(\Nette\Latte\Compiler $compiler) {
        $me = parent::install($compiler);
        $me->addMacro('linkThumbnail', 'echo $_presenter->getContext()->getByType("Arachne\Resources\Thumbnailer")->renderLinkThumbnail(%node.array)');
        $me->addMacro('thumbnail', 'echo $_presenter->getContext()->getByType("Arachne\Resources\Thumbnailer")->renderThumbnail(%node.array)');
        $me->addMacro('thumbnailLink', 'echo $_presenter->getContext()->getByType("Arachne\Resources\Thumbnailer")->ThumbnailLink(%node.array)');
    }

}