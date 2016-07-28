<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Script\ScriptEvents;
use Eloquent\Phony\Phony;

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
        $this->fileGetContents = Phony::stubGlobal('file_get_contents', __NAMESPACE__);
        $this->filePutContents = Phony::stubGlobal('file_put_contents', __NAMESPACE__);

        $this->fileGetContents->returns('<template %mode%>');

        $this->subject = new Plugin();
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
        it('replaces the autoloader', function () {
            $this->subject->activate($this->composer->get(), $this->io->get());
            $this->subject->onPostAutoloadDump(true);

            $this->io->write->calledWith('Recoil code instrumentation is enabled');

            $this->copy->calledWith(
                '/tmp/vendor/autoload.php',
                '/tmp/vendor/autoload.original.php'
            );

            $this->filePutContents->calledWith(
                '/tmp/vendor/autoload.php',
                "<template 'all'>"
            );
        });

        it('does not replace the autoloader when --no-dev is specified', function () {
            $this->subject->activate($this->composer->get(), $this->io->get());
            $this->subject->onPostAutoloadDump(false);

            $this->io->write->calledWith('Recoil code instrumentation is disabled (installing with --no-dev)');

            $this->copy->never()->called();
            $this->fileGetContents->never()->called();
            $this->filePutContents->never()->called();
        });

        it('does not replace the autoloader when disabled in composer.json', function () {
            $this->package->getExtra->returns(['recoil' => ['instrumentation' => 'none']]);
            $this->subject->activate($this->composer->get(), $this->io->get());
            $this->subject->onPostAutoloadDump(true);

            $this->io->write->calledWith('Recoil code instrumentation is disabled (in composer.json)');

            $this->copy->never()->called();
            $this->fileGetContents->never()->called();
            $this->filePutContents->never()->called();
        });
    });

});
