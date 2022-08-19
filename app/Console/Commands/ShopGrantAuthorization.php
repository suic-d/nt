<?php

namespace App\Console\Commands;

use App\Helpers\Europe;
use App\Helpers\FarEast;
use App\Helpers\NorthAmerica;
use App\Helpers\ShopGrantAuthAbstract;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Input\InputArgument;

class ShopGrantAuthorization extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'shop-grant-auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '亚马逊店铺授权';

    /**
     * @var ShopGrantAuthAbstract
     */
    protected $instance;

    protected $accessKey = [
        '305100263759' => ['AKIAIGLIYUC7Y3UJ4O4A', 'pXFm13AbP4NTF/v1PNpzype85RgH8UR65Ko6BHd0'],
        '538599684490' => ['AKIAIBBBHMIDMQX5QUAQ', 'E5lkkCZcfYqxmGYxi54oNsadjN1dLI8cWd5WSSGD'],
        '234274012030' => ['AKIAIVP3X6GUYJ47HJ7Q', 'NnwSI4TkWPVJ/cLb7TdoQvN1+SeEJruzeyv353Zk'],
        '752765089621' => ['AKIAJ3UD6KUUR723XH7A', 'SyPJA9H6ZW388BjbXdwNR/SIQNK9IKRG05v5WSQ+'],
        '567821126754' => ['AKIAJQRSFSFFTY3437MA', 'dES2+0zNu/97mIO3wNAoWaHR4uX8AJk85QXPZ1qw'],
        '436401130846' => ['AKIAI2LSTUEPM3YH6N3A', 'RHFPjguDsL4Jh2lf+ejv3HAOb7F4tXkDfnJfqI6g'],
        '386368759733' => ['AKIAJOOZXK5RGIGPAGQQ', 'h8amagVRQgOIyt3wEPy6xg+OK2pBQDysLy/QRPMO'],
        '023371833905' => ['AKIAIS4X6OU774MJRMMQ', 'x3WS8f74XA10NLBW3e7yp6E4pic1tOiEHWHimtHW'],
        '524403662774' => ['AKIAI7SMZT5ACRHZY4WA', 'rpjDL78QqUj9xtanGIJDbynociB0rCcJfaUWPwg4'],
        '283613931751' => ['AKIAJCCURRCWZKURQJWQ', 'SvJiZJatWlzs3jLDOFIUHvz1GIXbTvTjlEqgBmyW'],
        '546597184699' => ['AKIAJYQR5RPQ4PTHFW2Q', 'pHqDs0O7dkMBNjk++9vTDL6M+0MJLGTTElyZsQdK'],
    ];

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->refreshToken();
    }

    public function refreshToken()
    {
        $file = '/www/20220426.xlsx';
        $worksheet = IOFactory::load($file)->getActiveSheet();
        $highestRow = $worksheet->getHighestDataRow();
        for ($row = $this->argument('offset'); $row <= $highestRow; ++$row) {
            $account = trim($worksheet->getCell('A'.$row)->getValue());
            $area = trim($worksheet->getCell('B'.$row)->getValue());
            $merchantId = trim($worksheet->getCell('C'.$row)->getValue());
            $refreshToken = trim($worksheet->getCell('D'.$row)->getValue());

            echo str_repeat('=', 50).' 亚马逊'.$account.$area.' '.str_repeat('=', 50), PHP_EOL;
            ShopGrantAuthAbstract::save('-- 亚马逊'.$account.$area.PHP_EOL);

            switch ($area) {
                case '北美':
                    (new NorthAmerica())->refreshToken($account, $merchantId, $refreshToken);

                    break;

                case '欧洲':
                    (new Europe())->refreshToken($account, $merchantId, $refreshToken);

                    break;

                case '远东':
                    (new FarEast())->refreshToken($account, $merchantId, $refreshToken);

                    break;
            }
        }
    }

    public function auth()
    {
        $file = '/www/20210820.xlsx';
        $worksheet = IOFactory::load($file)->getActiveSheet();
        $highestRow = $worksheet->getHighestDataRow();
        for ($row = $this->argument('offset'); $row <= $highestRow; ++$row) {
            $account = trim($worksheet->getCell('A'.$row)->getValue());
            $area = trim($worksheet->getCell('B'.$row)->getValue());
            $merchantId = trim($worksheet->getCell('C'.$row)->getValue());
            $token = trim($worksheet->getCell('D'.$row)->getValue());
            $id = trim($worksheet->getCell('E'.$row)->getValue());
            if (!isset($this->accessKey[$id])) {
                continue;
            }

            $accessKeyId = $this->accessKey[$id][0];
            $secretKey = $this->accessKey[$id][1];

            echo '-- 亚马逊'.$account.$area, PHP_EOL;
            ShopGrantAuthAbstract::save('-- 亚马逊'.$account.$area.PHP_EOL);

            switch ($area) {
                case '北美':
                    (new NorthAmerica())->auth($account, $merchantId, $accessKeyId, $secretKey, $token);

                    break;

                case '欧洲':
                    (new Europe())->auth($account, $merchantId, $accessKeyId, $secretKey, $token);

                    break;

                case '远东':
                    (new FarEast())->auth($account, $merchantId, $accessKeyId, $secretKey, $token);

                    break;
            }
        }
    }

    public function getArguments()
    {
        return [
            ['offset', InputArgument::OPTIONAL, '起始行', 1],
        ];
    }
}
