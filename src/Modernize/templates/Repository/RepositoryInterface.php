<?php

/**
 * Project: WPPluginModernizer
 * File: RepositoryTemplate.php
 * Author: WPPluginModernizer
 * Date: 3/5/24
 */

$template = <<<TEMPLATE
<?php

namespace {{namespace}};

interface {{repositoryInterfaceName}}
{
    /**
     * Describe the method and its purpose here.
     *
     * @param type \$parameter Description
     * @return type
     */
    public function exampleMethod(array \$parameter): array;
}
TEMPLATE;

return $template;

