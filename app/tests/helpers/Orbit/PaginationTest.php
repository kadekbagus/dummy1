<?php
/**
 * Unit test for Orbit\Pagination class.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Orbit\Pagination;

class PaginationTest extends OrbitTestCase
{
    protected $config = [
        'per_page'  => [
            'default'   => 'x.pagination.%s.per_page',
            'fallback'  => 'x.pagination.per_page'
        ],

        'max_record'    => [
            'default'   => 'x.pagination.%s.max_record',
            'fallback'  => 'x.pagination.max_record'
        ]
    ];

    public function testInstance()
    {
        $pg = Pagination::create();
        $this->assertInstanceOf('Orbit\Pagination', $pg);
    }

    public function testSetPerPageValueConfigNotExists()
    {
        $pg = Pagination::create($this->config);

        $defaultPerPage = $pg->perPage;
        $perPage = $pg->setPerPage('mytest')->perPage;

        $this->assertSame($defaultPerPage, $perPage);
    }

    public function testSetPerPageValueConfigExists()
    {
        $listname = 'mytest';
        $expect = 99;
        $perPageConfig = sprintf($this->config['per_page']['default'], $listname);
        Config::set($perPageConfig, $expect);

        $pg = Pagination::create($this->config);
        $perPage = $pg->setPerPage($listname)->perPage;
        $this->assertSame($expect, $perPage);
    }

    public function testSetPerPageValueConfigNotExistsFallbackToConfigValue()
    {
        $listname = 'nonexistentconfig';
        $expect = 101;
        $maxRecordConfig = $this->config['per_page']['fallback'];

        Config::set($maxRecordConfig, $expect);

        $pg = Pagination::create($this->config);
        $perPage = $pg->setPerPage($listname)->perPage;
        $this->assertSame($expect, $perPage);
    }

    public function testSetMaxRecordValueConfigNotExists()
    {
        $pg = Pagination::create($this->config);

        $defaultMaxRecord = $pg->maxRecord;
        $maxRecord = $pg->setPerPage('mytest')->maxRecord;

        $this->assertSame($defaultMaxRecord, $maxRecord);
    }

    public function testSetMaxRecordValueConfigExists()
    {
        $listname = 'mytest';
        $expect = 222;
        $maxRecordConfig = sprintf($this->config['max_record']['default'], $listname);
        Config::set($maxRecordConfig, $expect);

        $pg = Pagination::create($this->config);
        $maxRecord = $pg->setMaxRecord($listname)->maxRecord;
        $this->assertSame($expect, $maxRecord);
    }

    public function testSetMaxRecordValueConfigNotExistsFallbackToConfigValue()
    {
        $listname = 'nonexistent2';
        $expect = 333;
        $maxRecordConfig = $this->config['max_record']['fallback'];
        Config::set($maxRecordConfig, $expect);

        $pg = Pagination::create($this->config);
        $maxRecord = $pg->setMaxRecord($listname)->maxRecord;
        $this->assertSame($expect, $maxRecord);
    }
}
