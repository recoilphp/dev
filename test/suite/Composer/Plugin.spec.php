<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Script\ScriptEvents;
use Eloquent\Phony\Phony;
use ReflectionClass;

describe(Plugin::class, function () {

    beforeEach(function () {
        $this->composer = Phony::mock(Composer::class);
        $this->io = Phony::mock(IOInterface::class);
        $this->config = Phony::mock(Config::class);
        $this->package = Phony::mock(RootPackageInterface::class);

        $this->composer->getPackage->returns($this->package);
        $this->composer->getConfig->returns($this->config);
        $this->package->getExtra->returns([]);
        $this->config->get->with('vendor-dir')->returns('/tmp/vendor');

        $this->copy = Phony::stubGlobal('copy', __NAMESPACE__);
        $this->fileExists = Phony::stubGlobal('file_exists', __NAMESPACE__);
        $this->unlink = Phony::stubGlobal('unlink', __NAMESPACE__);
        $this->fileGetContents = Phony::stubGlobal('file_get_contents', __NAMESPACE__);
        $this->filePutContents = Phony::stubGlobal('file_put_contents', __NAMESPACE__);

        $reflector = new ReflectionClass(Plugin::class);
        $this->templateFile = dirname($reflector->getFilename()) . '/../../res/autoload.php.tmpl';

        $this->fileExists->returns(true);
        $this->fileGetContents->returns('<template %mode%>');

        $this->subject = new Plugin();
        $this->subject->activate($this->composer->get(), $this->io->get());
    });

    afterEach(function () {
        Phony::restoreGlobalFunctions();
    });

    describe('::getSubscribedEvents()', function () {
        it('subscribes to the POST_AUTOLOAD_DUMP event', function () {
            expect(Plugin::getSubscribedEvents())->to->equal(
                [ScriptEvents::POST_AUTOLOAD_DUMP => 'onPostAutoloadDump']
            );
        });
    });

    describe('->onPostAutoloadDump()', function () {
        context('when instrumentation is enabled', function () {
            beforeEach(function () {
                $this->subject->onPostAutoloadDump(true);
            });

            it('prints a message', function () {
                $this->io->write->calledWith('Recoil code instrumentation is enabled');
            });

            it('replaces the composer autoloader', function () {
                $this->copy->calledWith(
                    '/tmp/vendor/autoload.php',
                    '/tmp/vendor/autoload.uninstrumented.php'
                );

                $this->fileGetContents->calledWith($this->templateFile);

                $this->filePutContents->calledWith(
                    '/tmp/vendor/autoload.php',
                    "<template 'all'>"
                );
            });

            it('does not remove the uninstrumented autoloader', function () {
                $this->unlink->never()->called();
            });
        });

        context('when instrumentation is disabled due to --no-dev', function () {
            it('prints a message', function () {
                $this->subject->onPostAutoloadDump(false);

                $this->io->write->calledWith('Recoil code instrumentation is disabled (installing with --no-dev)');
            });

            it('does not replace the composer autoloader', function () {
                $this->subject->onPostAutoloadDump(false);

                $this->copy->never()->called();
                $this->filePutContents->never()->called();
            });

            it('removes the copy of the original composer file', function () {
                $this->subject->onPostAutoloadDump(false);

                $this->unlink->calledWith('/tmp/vendor/autoload.uninstrumented.php');
            });

            it('does not remove copy of the original composer file if it does not exist', function () {
                $this->fileExists->with('/tmp/vendor/autoload.uninstrumented.php')->returns(false);

                $this->subject->onPostAutoloadDump(false);

                $this->unlink->never()->called();
            });
        });

        context('when instrumentation is disabled in composer.json', function () {
            beforeEach(function () {
                $this->package->getExtra->returns(['recoil' => ['instrumentation' => 'none']]);
                $this->subject->activate($this->composer->get(), $this->io->get());
            });

            it('prints a message', function () {
                $this->subject->onPostAutoloadDump(true);

                $this->io->write->calledWith('Recoil code instrumentation is disabled (in composer.json)');
            });

            it('does not replace the composer autoloader', function () {
                $this->subject->onPostAutoloadDump(true);

                $this->copy->never()->called();
                $this->filePutContents->never()->called();
            });

            it('removes the copy of the original composer file', function () {
                $this->subject->onPostAutoloadDump(true);

                $this->unlink->calledWith('/tmp/vendor/autoload.uninstrumented.php');
            });

            it('does not remove copy of the original composer file if it does not exist', function () {
                $this->fileExists->with('/tmp/vendor/autoload.uninstrumented.php')->returns(false);

                $this->subject->onPostAutoloadDump(true);

                $this->unlink->never()->called();
            });
        });

        context('when instrumentation is disabled in composer.json', function () {
            beforeEach(function () {
                $this->fileExists->with($this->templateFile)->returns(false);
            });

            it('prints a message', function () {
                $this->subject->onPostAutoloadDump(true);

                $this->io->write->calledWith('Recoil code instrumentation is disabled (uninstalling recoil/dev)');
            });

            it('does not replace the composer autoloader', function () {
                $this->subject->onPostAutoloadDump(true);

                $this->copy->never()->called();
                $this->filePutContents->never()->called();
            });

            it('removes the copy of the original composer file', function () {
                $this->subject->onPostAutoloadDump(true);

                $this->unlink->calledWith('/tmp/vendor/autoload.uninstrumented.php');
            });

            it('does not remove copy of the original composer file if it does not exist', function () {
                $this->fileExists->with('/tmp/vendor/autoload.uninstrumented.php')->returns(false);

                $this->subject->onPostAutoloadDump(true);

                $this->unlink->never()->called();
            });
        });
    });

});
