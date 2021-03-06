<?php

/*
 * This file is part of PHPExifTool.
 *
 * (c) 2012 Romain Neutron <imprec@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPExiftool\Driver\Tag\Flash;

use JMS\Serializer\Annotation\ExclusionPolicy;
use PHPExiftool\Driver\AbstractTag;

/**
 * @ExclusionPolicy("all")
 */
class AudioBitsPerSample extends AbstractTag
{

    protected $Id = 'Bit6';

    protected $Name = 'AudioBitsPerSample';

    protected $FullName = 'Flash::Audio';

    protected $GroupName = 'Flash';

    protected $g0 = 'Flash';

    protected $g1 = 'Flash';

    protected $g2 = 'Audio';

    protected $Type = '?';

    protected $Writable = false;

    protected $Description = 'Audio Bits Per Sample';

}
