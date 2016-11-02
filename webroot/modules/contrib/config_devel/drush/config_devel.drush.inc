<?php

/**
 * @file
 * Configuration development module drush integration.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\InstallStorage;

/**
 * Implements hook_drush_command().
 */
function config_devel_drush_command() {
  $items = array();

  $description = '';
  $description .= "Write back configuration to module's config/install directory." . "\n";
  $description .= "List which configuration settings you want to export in the" . "\n";
  $description .= "module's info file by listing them under 'config_devel', as shown below:"  . "\n";
  $description .= "\n";
  $description .= "config_devel:"  . "\n";
  $description .= "  - entity.view_display.node.article.default"  . "\n";
  $description .= "  - entity.view_display.node.article.teaser"  . "\n";
  $description .= "  - field.instance.node.article.body"  . "\n";

  $items['config-devel-export'] = array(
    'description' => $description,
    'arguments' => array(
      'module' => 'Module machine name.',
    ),
    'options' => array(
    ),
    'required-arguments' => TRUE,
    'examples' => array(
      'drush config-devel-export MODULE_NAME' => 'Write back configuration to the specified module, based on .info file.',
    ),
    'aliases' => array('cde', 'cd-em'),
  );

  $items['config-devel-import'] = array(
    'description' => $description,
    'arguments' => array(
      'module' => 'Module machine name.',
    ),
    'options' => array(
    ),
    'required-arguments' => TRUE,
    'examples' => array(
      'drush config-devel-import MODULE_NAME' => 'Import configuration from the specified module into the active storage, based on .info file.',
    ),
    'aliases' => array('cdi', 'cd-im'),
  );

  $items['config-devel-import-one'] = array(
    'description' => $description,
    'arguments' => array(
      'path' => 'Config file name.',
    ),
    'options' => array(
    ),
    'required-arguments' => TRUE,
    'examples' => array(
      'drush config-devel-import-one system.site.yml' => 'Import the contents of system.site.yml into the config object system.site.',
      'drush config-devel-import-one system.site' => 'Import the standard input into the config object system.site. Helpful for scripting copying to remote',
    ),
    'aliases' => array('cdi1', 'cd-i1'),
  );

  $items['config-devel-module-dependencies'] = array(
    'description' => "List the dependencies of an extension's default configuration.",
    'arguments' => array(
      'module' => 'Module machine name.',
    ),
    'options' => array(
      'active' => 'List dependencies for the config according to what is in the active storage rather than in the default configuration files.',
      'type' => 'The type of the extension; one of \'core\', \'profile\', \'module\' (default), \'theme\', or \'theme_engine\'.',
      'subdir' => 'Sub-directory of configuration to list dependencies for. Defaults to "install", but could also be "optional".',
      'direction' => 'Optionally list dependants (in active storage) rather than dependencies by setting this to \'reverse\', or list both with \'both\'.',
      'recursion-limit' => 'Limit recursion.',
    ),
    'required-arguments' => TRUE,
    'examples' => array(
      'drush config-devel-module-dependencies MODULE_NAME --active=1' => 'List the dependencies of the configuration',
    ),
    'aliases' => array('cd-md'),
    'outputformat' => array(
      'default' => 'yaml',
    ),
  );

  return $items;
}

/**
 * Drush command callback.
 */
function drush_config_devel_export($extension) {
  // Determine the type of extension we're dealing with.
  $type = drush_config_devel_get_type($extension);

  if ($type) {
    // Get the config
    $config = drush_config_devel_get_config($type, $extension);

    // Process config
    if (isset($config['install'])) {
      drush_config_devel_process_config($config['install'], $type, $extension, InstallStorage::CONFIG_INSTALL_DIRECTORY);
    }

    // If we have any optional configuration, process that as well.
    if (isset($config['optional'])) {
      drush_config_devel_process_config($config['optional'], $type, $extension, InstallStorage::CONFIG_INSTALL_DIRECTORY);
    }
  }
  else {
    drush_set_error("Couldn't export configuration. The '$extension' extension is not enabled.");
  }
}

/**
 * Drush command callback.
 */
function drush_config_devel_import($extension) {
  // Determine the type of extension we're dealing with.
  $type = drush_config_devel_get_type($extension);

  if ($type) {
    // Get the config
    $config = drush_config_devel_get_config($type, $extension);

    // Import config
    if (isset($config['install'])) {
      drush_config_devel_import_config($config['install'], $type, $extension, InstallStorage::CONFIG_INSTALL_DIRECTORY);
    }

    // Import optional config
    if (isset($config['optional'])) {
      drush_config_devel_import_config($config['optional'], $type, $extension, InstallStorage::CONFIG_INSTALL_DIRECTORY);
    }
  }
  else {
    drush_set_error("Couldn't import configuration. The '$extension' extension is not enabled.");
  }
}

/**
 * Drush command callback.
 */
function drush_config_devel_import_one($path) {
  $contents = '';
  if (!file_exists($path)) {
    if (substr($path, -4) != '.yml') {
      $contents = file_get_contents('php://stdin');
    }
    elseif (!empty($_SERVER['PWD'])) {
      $path = $_SERVER['PWD'] . '/' . trim($path, '/');
    }
  }
  if ($contents || file_exists($path)) {
    \Drupal::service('config_devel.auto_import_subscriber')->importOne($path, '', $contents);
  }
  else {
    drush_log('file not found', 'error');
    exit;
  }
}

/**
 * Exports a list of configuration entities.
 *
 * @param array $config_list
 *   An array of configuration entities.
 * @param string $type
 *   The type of extension we're exporting, one of module or theme.
 * @param string $extension
 *   The module, theme or install profile we're exporting.
 * @param string $directory
 *   The directory we're exporting to.
 */
function drush_config_devel_process_config($config_list, $type, $extension, $directory) {
  $config_path = drupal_get_path($type, $extension) . "/$directory";

  // Ensure the directory always exists.
  if (!file_exists($config_path) && !\Drupal::service('file_system')->mkdir($config_path, NULL, TRUE)) {
    drush_log(sprintf('The %s directory could not be created', $config_path));
    return FALSE;
  }

  foreach ($config_list as $name) {
    $config = \Drupal::config($name);
    if ($config->isNew()) {
      drush_log('Config ' . $name . ' not found', 'error');
    }
    else {
      $file_names = array($config_path . '/' . $name . '.yml');
      \Drupal::service('config_devel.writeback_subscriber')->writeBackConfig($config, $file_names);
    }
  }
}

/**
 * Imports a list of configuration entities
 *
 * @param array $config_list
 *   An array of configuration entities.
 * @param string $type
 *   The type of extension we're exporting, one of module or theme.
 * @param string $extension
 *   The module, theme or install profile we're exporting.
 * @param string $directory
 *   The directory we're exporting to.
 */
function drush_config_devel_import_config($config_list, $type, $extension, $directory) {
  $config_path = drupal_get_path($type, $extension) . "/$directory";
  foreach ($config_list as $name) {
    $file_name = $config_path . '/' . $name . '.yml';
    drush_config_devel_import_one($file_name);
  }
}

/**
 * Gets the config.
 *
 * @param  string $type
 *   module, theme or profile
 * @param  string $extension
 *   extension name
 * @param  boolean $include_disabled
 *   Optionally support uninstalled modules.
 * @return array
 *   An array containing install and optional config
 */
function drush_config_devel_get_config($type, $extension, $include_disabled = FALSE) {
  $info = array();

  if ($extension_path = drupal_get_path($type, $extension)) {
    $filename = $extension_path . '/' . $extension .'.info.yml';
    $info = \Drupal::service('info_parser')->parse($filename);
  }
  elseif ($include_disabled && $type === 'module') {
    // Check against list of disabled modules.
    $modules = system_rebuild_module_data();
    if (isset($modules[$extension])) {
      $info = $modules[$extension]->info;
    }
  }

  $config = array();
  if (isset($info['config_devel'])) {
    // Keep backwards compatibility for the old format.
    if (!isset($info['config_devel']['install'])) {
      $info['config_devel']['install'] = $info['config_devel'];
    }

    $config['install'] = $info['config_devel']['install'];

    // If we have any optional configuration, fetch that as well.
    if (isset($info['config_devel']['optional'])) {
      $config['optional'] = $info['config_devel']['optional'];
    }
  }

  return $config;
}

/**
 * Gets the type for the given extension.
 *
 * @param  string $extension
 *   extension name
 * @return string
 *   module, theme, profile, or FALSE if no valid extension provided.
 */
function drush_config_devel_get_type($extension) {
  $type = NULL;
  if (\Drupal::moduleHandler()->moduleExists($extension)) {
    $type = 'module';
  }
  elseif (\Drupal::service('theme_handler')->themeExists($extension)) {
    $type = 'theme';
  }
  elseif (drupal_get_profile() === $extension) {
    $type = 'profile';
  }

  return $type;
}

/**
 * Drush command callback.
 */
function drush_config_devel_module_dependencies($extension) {
  // Get the extension's configuration.
  $type = drush_get_option('type', 'module');
  $listed_config = drush_config_devel_get_config($type, $extension, TRUE);

  if ($listed_config) {
    // Initialize array to be returned.
    $dependencies = array(
      'module' => array(),
      'config' => array(),
    );
    $subdir = drush_get_option('subdir', 'install');

    // Collect dependants on the listed config to get dependencies of (as well
    // as the listed config), or if set for the default direction, just the
    // listed config. The $config_to_check array will use config names as keys
    // in order to avoid duplicate items.
    $config_to_check = array();
    if (isset($listed_config[$subdir])) {
      $limit = drush_get_option('recursion-limit', 99);
      $direction = drush_get_option('direction', 'default');

      // The dependency manager will be needed to get dependants.
      /** @var Drupal\Core\Config\Entity\ConfigDependencyManager $dependency_manager */
      if ($direction != 'default') {
        $dependency_manager = \Drupal::service('config.manager')
          ->getConfigDependencyManager();
      }

      foreach ($listed_config[$subdir] as $name) {
        $config_to_check[$name] = $name;

        if ($direction != 'default') {
          // Recursively fetch configuration entities that are dependent on this
          // configuration entity (i.e. reverse dependencies).
          if ($config_dependants = drush_config_devel_get_config_dependants($name, $dependency_manager, $limit)) {
            $config_to_check += array_combine($config_dependants, $config_dependants);
          }
        }
      }

      // Now get dependencies of the configuration to check.
      if ($direction == 'both' || $direction == 'default') {
        $active = drush_get_option('active');
        $config_path = drupal_get_path($type, $extension) . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY . '/';

        foreach ($config_to_check as $name) {
          // Add this config item before continuing to check its dependencies.
          $dependencies['config'][$name] = $name;

          if ($active) {
            $config_dependencies = drush_config_devel_get_config_dependencies_from_active_storage($name, $limit);
          }
          else {
            $config_dependencies = drush_config_devel_get_config_dependencies_from_filesystem($config_path, $name, $limit);
          }

          // Add this config's dependencies to the full list to be returned.
          foreach ($config_dependencies as $dependency_type => $type_dependencies) {
            if (!isset($dependencies[$dependency_type])) {
              $dependencies[$dependency_type] = array();
            }
            $dependencies[$dependency_type] += $type_dependencies;
          }
        }

        // Exclude 'core' and the specified extension from the full list.
        unset($dependencies['module']['core']);
        unset($dependencies[$type][$extension]);
      }
      else {
        // Only the dependants are needed.
        $dependencies['config'] = array_keys($config_to_check);
      }

      // Exclude the originally listed config from the dependencies list.
      $dependencies['config'] = array_diff($dependencies['config'], $listed_config[$subdir]);

      // Sort and filter dependency lists, and finally change to simple indexed
      // array lists. This means the output can then be directly copied for use
      // in a .info.yml file if the output format is YAML.
      foreach (array_keys($dependencies) as $dependency_type) {
        if ($dependencies[$dependency_type]) {
          sort($dependencies[$dependency_type]);
          $dependencies[$dependency_type] = array_values($dependencies[$dependency_type]);
        }
        else {
          unset($dependencies[$dependency_type]);
        }
      }
    }

    return $dependencies;
  }
  else {
    drush_set_error("Couldn't list configuration dependencies. The '$extension' extension could not be found or has no configuration specified in its .info.yml file.");
  }
}

function drush_config_devel_get_config_dependants($name, $dependency_manager, $limit = NULL) {
  static $recursive_iterations = 0;
  static $checked = array();

  if (!isset($checked[$name])) {
    /** @var Drupal\Core\Config\Entity\ConfigDependencyManager $dependency_manager */
    if ($dependants = array_keys($dependency_manager->getDependentEntities('config', $name))) {
      // Use associative array to avoid duplicates.
      $dependants = array_combine($dependants, $dependants);

      $recursive_iterations++;
      if ($limit && $recursive_iterations < $limit) {
        $base_dependants = $dependants;
        foreach ($base_dependants as $dependant) {
          if ($sub_dependants = drush_config_devel_get_config_dependants($dependant, $dependency_manager, $limit)) {
            $dependants += $sub_dependants;
          }
        }
      }
      $recursive_iterations--;
    }

    $checked[$name] = $dependants;
  }

  return $checked[$name];
}

function drush_config_devel_get_config_dependencies_from_active_storage($name, $limit = NULL) {
  static $recursive_iterations = 0;
  static $checked = array();

  if (!isset($checked[$name])) {
    $config_dependencies = \Drupal::config($name)->get('dependencies');
    if ($config_dependencies && is_array($config_dependencies)) {
      foreach ($config_dependencies as $dependency_type => $type_dependencies) {
        // Use associative array to avoid duplicates.
        $config_dependencies[$dependency_type] = array_combine($type_dependencies, $type_dependencies);
      }

      // Recurse to find sub-dependencies.
      if (isset($config_dependencies['config'])) {
        $recursive_iterations++;
        if ($limit && $recursive_iterations < $limit) {
          foreach ($config_dependencies['config'] as $dependency) {
            $sub_dependencies = drush_config_devel_get_config_dependencies_from_active_storage($dependency, $limit);

            // Add this dependency's dependencies to the list to be returned.
            foreach ($sub_dependencies as $dependency_type => $type_dependencies) {
              if (!isset($config_dependencies[$dependency_type])) {
                $config_dependencies[$dependency_type] = array();
              }
              $config_dependencies[$dependency_type] += $type_dependencies;
            }
          }
        }
        $recursive_iterations--;
      }
    }
    else {
      $config_dependencies = array();
    }

    // Config provider is an implied module dependency.
    $config_provider = substr($name, 0, strpos($name, '.'));
    $config_dependencies['module'][$config_provider] = $config_provider;

    $checked[$name] = $config_dependencies;
  }

  return $checked[$name];
}

function drush_config_devel_get_config_dependencies_from_filesystem($config_path, $name, $limit = NULL) {
  static $recursive_iterations = 0;
  static $checked = array();

  if (!isset($checked[$name])) {
    $config_dependencies = array();
    if ($config_file = @file_get_contents($config_path . $name . '.yml')) {
      $config = Yaml::decode($config_file);
      if (is_array($config) && isset($config['dependencies']) && is_array($config['dependencies'])) {
        foreach ($config['dependencies'] as $dependency_type => $type_dependencies) {
          // Use associative array to avoid duplicates.
          $config_dependencies[$dependency_type] = array_combine($type_dependencies, $type_dependencies);
        }

        // Recurse to find sub-dependencies.
        if (isset($config_dependencies['config'])) {
          $recursive_iterations++;
          if ($limit && $recursive_iterations < $limit) {
            foreach ($config_dependencies['config'] as $dependency) {
              $sub_dependencies = drush_config_devel_get_config_dependencies_from_filesystem($config_path, $dependency, $limit);

              // Add this dependency's dependencies to the list to be returned.
              foreach ($sub_dependencies as $dependency_type => $type_dependencies) {
                if (!isset($config_dependencies[$dependency_type])) {
                  $config_dependencies[$dependency_type] = array();
                }
                $config_dependencies[$dependency_type] += $type_dependencies;
              }
            }
          }
          $recursive_iterations--;
        }
      }
    }

    // Config provider is an implied module dependency.
    $config_provider = substr($name, 0, strpos($name, '.'));
    $config_dependencies['module'][$config_provider] = $config_provider;

    $checked[$name] = $config_dependencies;
  }

  return $checked[$name];
}
