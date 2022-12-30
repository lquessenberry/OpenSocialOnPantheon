# Update Helper

This module offers supporting functionalities to make configuration updates easier.

### Important notes

This module has Drush command. In order to execute it properly, you have to use Drush installed with your project.
In case of composer build, it's: `[project directory]/vendor/bin/drush`

Drush version has to be greater than 10.2.

### Provided features

Update helper module provides Drush command that will generate update configuration changes (it's called configuration update definition or CUD). Configuration update definition (CUD) will be stored in `config/update` directory of the module and it can be easily executed with update helper.

It's sufficient to execute `drush generate configuration-update` and follow instructions.
There are prompts that have to be complete, for example, the module name where all generated data will be saved (CUD file and update hook function), the description for update hook and so on.
Command will generate CUD file and save it in `config/update` folder of module and it will create update hook function in `<module_name>.install` file.
Additionally, new configurations will be exported to their corresponding YAML files.

### Checklist integration

Additionally, to the generation of configuration update definition and execution of it in update hooks, it's also possible to generate checklist entries for every executed configuration update. In order to use that functionality `update_helper_checklist` module has to be enabled. That module hooks in `generate configuration-update` over events and it will automatically provide additional options and checklist entry generation.

This functionality is really helpful for distributions and could be interesting for modules that comes with a lot of new configuration changes or update hooks.
For distributions, there is a proposal to use one single module for collection of updates. That module would contain all generated configuration update definitions (CUDs), all update hooks and also `updates_checklist.yml` file for all generated updates.

### How to create configuration update

To generate configuration update for configuration changes is quite simple and only a few steps should be followed:
1. Make clean installation of the previous version of the module or the distribution (version for which one you want to create configuration update, for example `8.x-1.x` branch).
2. Execute update hooks if it's necessary (for example: in case when there are dependency updates for module and/or core)
3. Export configuration, because that is state that will be on live systems before module/distribution update hooks are executed and we want to fill that gap with generated configuration update from update helper
4. Do your default development process (make code changes, module or core updates, adjusting of configuration to work with new code changes, etc.) - but do not export new configuration files during this process, that what generate configuration update command will do for you
5. Now is a moment to generate configuration update (CUD) file and update hook code. For that we have provided following drush command: `drush generate configuration-update`. Command will generate CUD file with all configuration changes for module or distribution and save it in `config/update` folder of the module you have provided, it will export new configuration changes into corresponding configuration YAML files and it will also create update hook function in `<module_name>.install` file. Answer 'no' when asked if you want to generate update from active configuration.
6. It's always a good time to make an additional check of generated code.

### How to prepare environment to create configuration updates if you already have exported new configuration YAML files (more robust approach, or so called reverse mode)

Workflow to generate configuration update for a module is following:
1. Export configuration files included in module with new changes (commit that to custom branch or stage it)
2. Make clean installation of Drupal with the previous version of the module (version for which one you want to create configuration update).
3. When module is installed and old configuration imported, make code update to previously created brunch or un-stage changes (with code update also configuration files will be updated, but not active configuration in database)
4. Execute update hooks if it's necessary (for example: in case when there are dependency updates for module and/or core)
5. Now is a moment to generate configuration update (CUD) file and update hook code. For that we have provided following drush command: `drush generate configuration-update`. Command will generate CUD file and save it in `config/update` folder of the module and it will create update hook function in `<module_name>.install` file. Answer 'yes' when asked if you want to generate update from active configuration.
6. After the command has finished it will display what files are modified and generated. It's always good to make an additional check of generated code.

Workflow to generate configuration update for a distribution is following:
1. Export configuration files included in distribution with new changes (commit that to custom branch or stage it)
2. Make clean installation of the previous version of the distribution (version for which one you want to create configuration update, for example `8.x-1.x` branch).
3. When distribution is installed and old configuration imported, make code update to previously created brunch or un-stage changes (with code update also configuration files will be updated, but not active configuration in database)
4. Execute update hooks if it's necessary (for example: in case when there are dependency updates for module and/or core)
5. Now is a moment to generate configuration update (CUD) file and update hook code. For that we have provided following drush command: `drush generate configuration-update`. Command will generate CUD file with all configuration changes for distribution and save it in `config/update` folder of the module you have provided and it will create update hook function in `<module_name>.install` file. Answer 'yes' when asked if you want to generate update from active configuration.
6. After the command has finished it will display what files are modified and generated. It's always good to make an additional check of generated code.
