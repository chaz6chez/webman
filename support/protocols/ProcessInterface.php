<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    chaz6chez<250220719@qq.com>
 * @copyright chaz6chez<250220719@qq.com>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace support\protocols;

use Workerman\Worker;

/**
 * @author cha6chez<250220719@qq.com>
 */
interface ProcessInterface
{

    /**
     * Emitted when worker processes start.
     *
     * @param Worker $worker
     * @return mixed
     */
    public function onWorkerStart(Worker $worker);

    /**
     * Emitted when worker processes stoped.
     *
     * @param Worker $worker
     * @return mixed
     */
    public function onWorkerStop(Worker $worker);

    /**
     * Emitted when worker processes get reload signal.
     *
     * @param Worker $worker
     * @return mixed
     */
    public function onWorkerReload(Worker $worker);

    /**
     * Emitted when worker processes exited.
     *
     * @param Worker $worker
     * @param $status
     * @param $pid
     * @return mixed
     */
    public function onWorkerExit(Worker $worker, $status, $pid);
}