<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

context('api/select', function () {
    beforeEach(function () {
        $this->streams = [];
        $this->files = [];

        for ($i = 0; $i < 2; ++$i) {
            $temp = tempnam(sys_get_temp_dir(), 'recoil-test-fifo-');
            unlink($temp);
            posix_mkfifo($temp, 0644);
            $stream = fopen($temp, 'w+'); // must be w+ (read/write) to prevent blocking
            stream_set_blocking($stream, false);

            $this->streams[] = $stream;
            $this->files[] = $temp;
        }

        list($this->stream1, $this->stream2) = $this->streams;
    });

    afterEach(function () {
        foreach ($this->streams as $stream) {
            fclose($stream);
        }
        foreach ($this->files as $file) {
            unlink($file);
        }
    });

    rit('returns an array of streams that are ready to read', function () {
        fwrite($this->stream2, '<value>');

        list($readable) = yield Recoil::select($this->streams, []);

        expect($readable)->to->equal([$this->stream2]);
    });

    rit('returns an array of streams that are ready to write', function () {
        // fill the write buffer
        do {
            $bytes = fwrite(
                $this->stream1,
                str_repeat('.', 8192)
            );
        } while ($bytes > 0);

        list(, $writable) = yield Recoil::select([], $this->streams);

        expect($writable)->to->equal([$this->stream2]);
    });

    rit('stops waiting for the stream when the strand is terminated', function () {
        $strand = yield Recoil::execute(function () {
            yield Recoil::select([$this->stream1], []);
            expect(false)->to->be->ok('strand was not terminated');
        });

        yield;

        $strand->terminate();
    });
});
