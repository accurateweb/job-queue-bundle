<?php
/**
 *  (c) 2019 ИП Рагозин Денис Николаевич. Все права защищены.
 *
 *  Настоящий файл является частью программного продукта, разработанного ИП Рагозиным Денисом Николаевичем
 *  (ОГРНИП 315668300000095, ИНН 660902635476).
 *
 *  Алгоритм и исходные коды программного кода программного продукта являются коммерческой тайной
 *  ИП Рагозина Денис Николаевича. Любое их использование без согласия ИП Рагозина Денис Николаевича рассматривается,
 *  как нарушение его авторских прав.
 *   Ответственность за нарушение авторских прав наступает в соответствии с действующим законодательством РФ.
 */

namespace Accurateweb\JobQueueBundle\Service\BackgroundJobDispatcher;

use Accurateweb\JobQueueBundle\Event\BackgroundJobDispatcherEvent;
use Accurateweb\JobQueueBundle\Event\BackgroundJobEvent;
use Accurateweb\JobQueueBundle\Model\BackgroundJob;
use Accurateweb\JobQueueBundle\Service\BackgroundJob\BackgroundJobManager;
use Accurateweb\JobQueueBundle\Service\BackgroundJob\BackgroundJobPool;
use Accurateweb\JobQueueBundle\Service\BackgroundJob\DeferredBackgroundJobInterface;
use Accurateweb\JobQueueBundle\Service\Command\CommandLineLauncherFactory;
use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BackgroundJobDispatcher
{
  protected $backgroundJobManager;
  protected $logger;
  protected $eventDispatcher;
  protected $entityManager;
  protected $backgroundJobPool;
  protected $launcher;
  protected $commandLineLauncherFactory;
  protected $uid;

  protected $break=false;

  public function __construct (
    BackgroundJobManager $backgroundJobManager,
    EventDispatcherInterface $eventDispatcher,
    EntityManager $entityManager,
    BackgroundJobPool $backgroundJobPool,
    CommandLineLauncherFactory $commandLineLauncherFactory,
    $uid
  )
  {
    $this->backgroundJobManager = $backgroundJobManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->entityManager = $entityManager;
    $this->backgroundJobPool = $backgroundJobPool;
    $this->commandLineLauncherFactory = $commandLineLauncherFactory;
    $this->uid = $uid;
  }

  public function dispatch()
  {
    $this->eventDispatcher->dispatch('background_job.dispatch.start', new BackgroundJobDispatcherEvent());

    while(!$this->break)
    {
      $jobs = $this->backgroundJobManager->getQueuedJobs();

      if (!count($jobs))
      {
        sleep(30);
      }

      foreach ($jobs as $job)
      {
        $jobService = $this->backgroundJobPool->getBackgroundJob($job->getClsid());

        if ($jobService instanceof DeferredBackgroundJobInterface && !$jobService->isReady())
        {
          continue;
        }

        $jobService->initialize($job->getMetaData());
        $command = $jobService->getCommand();
//        $command = $job->getCommand();
        $job->setCommand($command);
        $job->setState(BackgroundJob::STATE_RUNNING);
        $job->setStartedAt(new \DateTime());
        $this->entityManager->persist($job);
        $this->entityManager->flush($job);

        $this->eventDispatcher->dispatch('job.start', new BackgroundJobEvent($jobService, $job));
        $launcher = $this->commandLineLauncherFactory->getLauncher($job);
        $statusCode = $launcher->startCommand($command);

        $job->setState(BackgroundJob::STATE_FINISHED);
        $job->setStatusCode($statusCode);
        $job->setFinishedAt(new \DateTime());
        $this->entityManager->persist($job);
        $this->entityManager->flush($job);

        $this->eventDispatcher->dispatch('job.end', new BackgroundJobEvent($jobService, $job));
      }

      /*
       * Очистим entityManager, чтобы он всю память не сожрал
       */
      $this->entityManager->clear();
      /*
       * Поставим задержку в пару секунд, чтобы постоянно повторяющиейся задачи не сожрали проц
       */
      sleep(2);
    }

    $this->eventDispatcher->dispatch('background_job.dispatch.end', new BackgroundJobDispatcherEvent());
  }

  /**
   * @return string
   */
  public function getUid ()
  {
    return $this->uid;
  }
}