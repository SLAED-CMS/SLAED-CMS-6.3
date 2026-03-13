<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

return [
    'filetype' => [
        '7zip' => <<<HTML
<a href="[src]" target="_blank" title="[title]">[title]</a>
HTML,
        'bmp'  => <<<HTML
<a rel="[rel]" title="[title]" href="[src]" class="screens"><img src="[tsrc]" style="max-width: [twidth]px; height: auto; float: [align]; margin: 0 5px 5px 0;" alt="[title]"></a>
HTML,
        'gif'  => <<<HTML
<a rel="[rel]" title="[title]" href="[src]" class="screens"><img src="[tsrc]" style="max-width: [twidth]px; height: auto; float: [align]; margin: 0 5px 5px 0;" alt="[title]"></a>
HTML,
        'gzip' => <<<HTML
<a href="[src]" target="_blank" title="[title]">[title]</a>
HTML,
        'jpeg' => <<<HTML
<a rel="[rel]" title="[title]" href="[src]" class="screens"><img src="[tsrc]" style="max-width: [twidth]px; height: auto; float: [align]; margin: 0 5px 5px 0;" alt="[title]"></a>
HTML,
        'jpg'  => <<<HTML
<a rel="[rel]" title="[title]" href="[src]" class="screens"><img src="[tsrc]" style="max-width: [twidth]px; height: auto; float: [align]; margin: 0 5px 5px 0;" alt="[title]"></a>
HTML,
        'm4a'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/mp4"></video>
HTML,
        'm4b'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/mp4"></video>
HTML,
        'm4p'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/mp4"></video>
HTML,
        'm4r'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/mp4"></video>
HTML,
        'm4v'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/mp4"></video>
HTML,
        'mp3'  => <<<HTML
<audio controls><source src="[src]" type="audio/mpeg"></audio>
HTML,
        'mp4'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/mp4"></video>
HTML,
        'oga'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/ogg"></video>
HTML,
        'ogg'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/ogg"></video>
HTML,
        'ogv'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/ogg"></video>
HTML,
        'ogx'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/ogg"></video>
HTML,
        'opus' => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/ogg"></video>
HTML,
        'png'  => <<<HTML
<a rel="[rel]" title="[title]" href="[src]" class="screens"><img src="[tsrc]" style="max-width: [twidth]px; height: auto; float: [align]; margin: 0 5px 5px 0;" alt="[title]"></a>
HTML,
        'rar'  => <<<HTML
<a href="[src]" target="_blank" title="[title]">[title]</a>
HTML,
        'spx'  => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/ogg"></video>
HTML,
        'swf'  => <<<HTML
 <embed src="[src]" height="[height]" width="[width]" type="application/x-shockwave-flash">
HTML,
        'wav'  => <<<HTML
<audio controls><source src="[src]" type="audio/wav"></audio>
HTML,
        'wave' => <<<HTML
<audio controls><source src="[src]" type="audio/wav"></audio>
HTML,
        'webm' => <<<HTML
<video width="[width]" height="[height]" controls><source src="[src]" type="video/webm"></video>
HTML,
        'zip'  => <<<HTML
<a href="[src]" target="_blank" title="[title]">[title]</a>
HTML,
    ],
];
