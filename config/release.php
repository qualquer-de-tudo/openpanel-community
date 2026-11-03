<?php

return json_decode(file_get_contents(resource_path('data/release.json')), true, 512, JSON_THROW_ON_ERROR);
