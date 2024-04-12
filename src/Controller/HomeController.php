<?php
namespace App\Controller;

use phpseclib3\Net\SSH2;
use phpseclib3\Exception\UnableToConnectException;
use App\Entity\Server;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
enum SSHRESULT
{
    case OK;
    case FAIL;
    case TRYINSTALL;
}

class HomeController extends AbstractController
{

    #[Route('/', name: 'monitor')]
    public function monitor(EntityManagerInterface $entityManager): Response
    {

        $servers = $entityManager->getRepository(Server::class)->findAll();

        return $this->render('monitor/monitor.html.twig', [
            'servers' => $servers
        ]);
    }

    #[Route('/server/{id}', name: 'server')]
    public function server(EntityManagerInterface $entityManager, $id): JsonResponse
    {
        $server = $entityManager->getRepository(Server::class)->find($id);

        $result = [
            'id' => $id,
            'running' => rand(0, 1),
            'cpu' => rand(0, 100),
            'temp' => rand(0, 90),
            'disk' => rand(34, 512),
            'disksize' => rand(34, 512),
            'allsky' => rand(0, 1),
            'allskytext' => 'Running'
        ];

        $command = './monitor.py -a';
        $sshResult = $this->sendSSHCommand($server->getIp(), $server->getUser(), $server->getPassword(), $command);
        if ($sshResult['result'] == SSHRESULT::OK) {
            $data = json_decode($sshResult['data']);

            $result['running'] = 1;
            $result['cpu'] = $data->cpu;
            $result['temp'] = $data->temp;
            $result['disk'] = $data->disk;
            $result['disksize'] = $data->disksize;
            $result['allsky'] = $data->allsky;
            $result['allskytext'] = $data->allskytext;
        } else {
            $result['running'] = 0;
        }

        return $this->json($result);

    }

    #[Route('/server/allsky/action/{action}/{id}', name: 'allsky')]
    public function action(EntityManagerInterface $entityManager, $action, $id): JsonResponse
    {
        $result = [];
        $remoteAction = '';
        switch ($action) {
            case 'start':
                $remoteAction = 'u';
                break;
            case 'stop':
                $remoteAction = 'd';
                break;
            case 'restart':
                $remoteAction = 'e';
                break;
        }

        if ($remoteAction !== '') {
            $server = $entityManager->getRepository(Server::class)->find($id);
            $command = './monitor.py -' . $remoteAction;
            $result = $this->sendSSHCommand($server->getIp(), $server->getUser(), $server->getPassword(), $command);
        }
        return $this->json($result);
    }

    #[Route('/server/action/{action}/{id}', name: 'pi')]
    public function serverAction(EntityManagerInterface $entityManager, $action, $id = 0): JsonResponse
    {
        $result = [];
        if ($id == 'this') {
            $command = '';
            switch ($action) {
                case 'reboot':
                    $command = '/sbin/reboot';
                    break;
                case 'shutdown':
                    $command = ['sudo', 'shutdown', '-h', '-now'];
                    break;
            }

            if ($command !== '') {
                dump($command);
                var_dump(exec($command)); die();
                //$process = new Process($command);
                //$process->mustRun();

                //dump($process); die();
            }

        } else {
            $remoteAction = '';
            switch ($action) {
                case 'reboot':
                    $remoteAction = 'r';
                    break;
                case 'shutdown':
                    $remoteAction = 's';
                    break;
                case 'allshutdown':
                    $servers = $entityManager->getRepository(Server::class)->findAll();
                    foreach ($servers as $server) {
                        if ($server->isGlobal()) {
                            $command = './monitor.py -s';
                            $result = $this->sendSSHCommand($server->getIp(), $server->getUser(), $server->getPassword(), $command);
                        }
                    }
                    break;
            }

            if ($remoteAction !== '') {
                $server = $entityManager->getRepository(Server::class)->find($id);
                $command = './monitor.py -' . $remoteAction;
                $result = $this->sendSSHCommand($server->getIp(), $server->getUser(), $server->getPassword(), $command);
            }
        }
        return $this->json($result);
    }

    private function isJSON($data)
    {
        $result = true;
        $data = json_decode($data);

        if ($data == null) {
            $result = false;
        }

        return $result;
    }

    private function tryInstall($host, $user, $password) {
        $result = false;

        try {
            $ssh = new SSH2($host, 22, 20);
            if (!$ssh->login($user, $password)) {
                $result = SSHRESULT::FAIL;
            } else {
                $command = 'wget -N https://raw.githubusercontent.com/Alex-developer/pimonitor/main/install.sh';
                $data = $ssh->exec($command);
                $command = 'chmod +x install.sh';
                $data = $ssh->exec($command);
                $command = './install.sh';
                $data = $ssh->exec($command);
                $command = 'wget -N https://raw.githubusercontent.com/Alex-developer/pimonitor/main/monitor.py';
                $data = $ssh->exec($command);                 
                $command = 'chmod +x monitor.py';
                $data = $ssh->exec($command);                 

                $result = true;
            }
        } catch (Exception $e) {
            $result = false;
        }
        return [
            'result' => $result,
            'data' => ''
        ];
    }

    private function sendSSHCommand($host, $user, $password, $command)
    {
        $data = '';

        $result = $this->doSendSSHCommand($host, $user, $password, $command);

        if ($result['result'] == SSHRESULT::TRYINSTALL) {
            $result = $this->tryInstall($host, $user, $password);
        }

        return [
            'result' => $result['result'],
            'data' => $result['data']
        ];
    }

    private function doSendSSHCommand($host, $user, $password, $command)
    {
        $result = SSHRESULT::OK;
        $data = '';

        try {
            $ssh = new SSH2($host, 22, 2);
            if (!$ssh->login($user, $password)) {
                $result = SSHRESULT::FAIL;
            } else {
                $data = $ssh->exec($command);
                if ($this->isJSON($data)) {
                    $result = SSHRESULT::OK;
                } else {
                    $result = SSHRESULT::TRYINSTALL;
                }
            }
        } catch (UnableToConnectException $e) {
            $result = SSHRESULT::FAIL;
        } catch (Exception $e) {
            $result = SSHRESULT::FAIL;
        }
    
        return [
            'result' => $result,
            'data' => $data
        ];
    }
}