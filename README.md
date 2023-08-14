# Dhii - PHP Project
[![Continuous Integration](https://github.com/dhii/php-project/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/dhii/php-project/actions/workflows/continuous-integration.yml)

A PHP starter to ease project creation

## Details
Use this project as a starter for your PHP library!

### Feaures
- **Docker** - Develop and test your plugin with Docker. Use an environment
    tailored for your plugin. See changes instantly in the browser. Build
    a Docker image containing a complete WordPress installation with your
    plugin and all pre-requisites.
    
- **PHPStorm** - Configuration for integrations of arguably the best PHP
    IDE out there, including:

    * **Composer** - Install and manage PHP dependencies on the correct version of PHP without leaving the IDE.
    * **PHPUnit** - Run tests and get reports directly in PHPStorm.
    * **xDebug** - Set breakpoints and inspect your code in PHPStorm.
    * **Code coverage** - See what has not been tested yet in a friendly GUI.
    
- **Static Code Analysis** - Maintain a consistent coding style, and catch problems early.

    * **[Psalm][]** - Inspects your code for problems.
    * **[PHPCS][]** - Checks your code style. [PHPCBF][] can fix some of them automatically.
    
- **Continuous Integration** - Automatically verify that all contributions comply with
    project standards with [GitHub Actions][].
    
### Usage

#### Getting Started
Use Composer to bootstrap your project.

1. Clone and install deps:

    ```bash
    composer create-project dhii/php-project my_project
    ```
   
   Here, `my_project` is the name of the project folder.
   
2. Customize project

    _Note_: Asterisk `*` below denotes that changing this value requires rebuild of the images in order
    to have effect on the dev environment.

    - Copy `.env.example` to `.env`.
    - `.env`:
        * `BASE_PATH` - If you are using [Docker Machine][], i.e. on any non-Linux system, set this
            to the absolute path to the project folder _inside the machine_. If you are on Linux,
            you do not need to change this.
        * `PROJECT_NAME` - Slug of your project. Used mainly for naming containers with [`container_name`][].
            This is helpful to run multiple projects on the same machine.
        * `PHP_BUILD_VERSION` - The version of PHP, on which the plugin will be _built_. This should
            correspond to the minimal PHP requirement of your plugin. Used to determine the tag of
            the [`php`][] image.
        * `PHP_TEST_VERSION`* - The version of PHP, on which the plugin will be _run_. This should
            correspond to the maximal PHP requirement of your plugin. Used to determine the tag of
            the [`php`][] image.
            
    - `composer.json`:
        * `name` - Name of your package.
        * `description` - Description of your package.
        * `authors` - You and/or your company details.
        * `require` - Your project's package and platform requirements. You may want to change the PHP
            version if your minimal requirement is different. Don't forget to update `PHP_BUILD_VERSION`
            in `.env`.
        * `require-dev` - Your project's development requirements. Tools for testing and code quality.

#### Updating Dependencies
Composer is installed into the `build` service's image. To run composer commands,
use `docker-compose run`. For example, to update dependencies you can run the following:

```bash
docker-compose run --rm build composer update
```

If you use PHPStorm, you can use the [composer integration][], as the project
is already configured for this.

#### Testing Code
This bootstrap includes PHPUnit. It is already configured, and you can test
that it's working by running the sample tests:

```bash
docker-compose run --rm test vendor/bin/phpunit
```

If you use PHPStorm, you can use its PHPUnit integration: right-click on any
test or folder inside the `tests` directory, and choose "Run". This will do
the same as the above command. Because the `test` service is used for tests,
they will be run with its PHP version, which should correspond to your project's
minimal requirements, but can be something else if you want to test on a system
that has different specs.

#### Debugging
The bootstrap includes xDebug in the `test` service of the Docker environment,
and PHPStorm configuration. To use it, right click on any test or folder within
the `tests` directory, and choose "Debug". This will run the tests with xDebug
enabled. If you receive the error about [`xdebug.remote_host`][] being set
incorrectly and suggesting to fix the error, fix it by setting that variable
to [your machine's IP address][] on the local network in the window that
pops up. After this, breakpoints in any code reachable by PHPUnit tests,
including the code of tests themselves, will cause execution to pause,
allowing inspection of code.

#### Static Analysis
- **Psalm**

    Run Psalm in project root:

    ```bash
    docker-compose run --rm test vendor/bin/psalm
    ```
  
    * Will also be run automatically on CI.
    * PHPStorm integration config included.
  
- **PHPCS**

    Run PHPCS/PHPCBF in project root:
    
    ```bash
    docker-compose run --rm test vendor/bin/phpcs -s --report-source --runtime-set ignore_warnings_on_exit 1
    docker-compose run --rm test vendor/bin/phpcbf
    ```
  
    * By default, uses [PSR-12][] and some rules from the [Slevomat Coding Standard][].
    * Will also be run automatically on CI.
    * PHPStorm integration config included.

        
[Docker Machine]: https://github.com/docker/machine
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[Slevomat Coding Standard]: https://github.com/slevomat/coding-standard
[Psalm]: https://psalm.dev/
[PHPCS]: https://github.com/squizlabs/PHP_CodeSniffer
[PHPCBF]: https://github.com/squizlabs/PHP_CodeSniffer/wiki/Fixing-Errors-Automatically
[GitHub Actions]: https://github.com/features/actions
[hosts file]: https://www.howtogeek.com/howto/27350/beginner-geek-how-to-edit-your-hosts-file/
[your machine's IP address]: https://www.whatismybrowser.com/detect/what-is-my-local-ip-address
[composer integration]: https://www.jetbrains.com/help/phpstorm/using-the-composer-dependency-manager.html#updating-dependencies
[`container_name`]: https://docs.docker.com/compose/compose-file/#container_name
[`php`]: https://hub.docker.com/_/php
[`docker-machine start`]: https://docs.docker.com/machine/reference/start/]
[`docker-machine env`]: https://docs.docker.com/machine/reference/env/
[`xdebug.remote_host`]: https://xdebug.org/docs/all_settings#remote_host
