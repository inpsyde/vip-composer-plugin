<?php

declare(strict_types=1);

return json_decode(file_get_contents(dirname(__DIR__) . '/json/sunrise-config.json'), true);
