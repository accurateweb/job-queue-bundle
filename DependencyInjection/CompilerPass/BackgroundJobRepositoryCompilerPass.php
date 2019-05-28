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

namespace Accurateweb\JobQueueBundle\DependencyInjection\CompilerPass;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\Extension;

class BackgroundJobRepositoryCompilerPass implements CompilerPassInterface
{
  public function process(ContainerBuilder $container)
  {
    $config = $config = $container->getExtensionConfig('accurateweb_job_queue');
    $config = $config[0];
    
    if (isset($config['configuration'], $config['configuration']['repository_service']))
    {
      $param = $config['configuration']['repository_service'];
      
      $repository = $container->getDefinition($param);
      $manager = $container->getDefinition('aw.bg_job.background_job_manager');
      $manager->setArgument(0, $repository);
    }
  }
  
}