<?php

namespace EasyAdminFriends\EasyAdminDashboardBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\Persistence\ManagerRegistry;

class DefaultController extends AbstractController
{
    private ManagerRegistry $entityManager;

    public function __construct(ManagerRegistry $entityManager) {
        $this->entityManager = $entityManager;
    }

    public function getLayoutTemplate()
    {
        $config = $this->getParameter('easy_admin_dashboard');
        $dashboard = $config ?? false;

        if(!empty($dashboard['layout'])){
            $layoutTemplatePath = $dashboard['layout'];
        }else{
            $layoutTemplatePath = '@EasyAdmin/page/content.html.twig';
        }

        return $layoutTemplatePath;
    }

    public function generateDashboardValues()
    {
        $config = $this->getParameter('easy_admin_dashboard');
        $dashboard = $config ?? false;

        if(!empty($dashboard['blocks'])){
            foreach($dashboard['blocks'] as $key=>$block){
                $dashboard['blocks'][$key]['permissions'] = $dashboard['blocks'][$key]['permissions'] ?? ['ROLE_USER'];
                if(!empty($block['items'])){
                    foreach($block['items'] as $k=>$item){
                        if(!empty($item['query'])){
                            $res = $this->executeCustomQuery($item['class'], $item['query']);
                        }else {
                            $res = $this->getBlockCount($item['class'], !empty($item['dql_filter']) ? $item['dql_filter'] : false);
                        }
                        $dashboard['blocks'][$key]['items'][$k]['res'] = $res;

                        $dashboard['blocks'][$key]['items'][$k]['title'] = $item['title'];

                        if(!empty($item['entity'])){
                            $entity = $item['entity'];
                        }else {
                            $entity = $this->guessEntityFromClass($item['class']);
                        }
                        $dashboard['blocks'][$key]['items'][$k]['entity'] = $entity;

                        $dashboard['blocks'][$key]['items'][$k]['permissions'] = $dashboard['blocks'][$key]['items'][$k]['permissions'] ?? $dashboard['blocks'][$key]['permissions'];
                    }
                }
            }
        }

        return $dashboard;
    }

    private function guessEntityFromClass($classname)
    {
        $entity_name = substr($classname, strrpos($classname, '\\') + 1);
        return (string) $entity_name;
    }

    private function getBlockCount($class, $dql_filter)
    {
        $this->em = $this->entityManager->getManagerForClass($class);

        $qb = $this->em->createQueryBuilder('entity');
        $qb->select('count(entity.id)');
        $qb->from($class, 'entity');

        if($dql_filter){
            $qb->where($dql_filter);
        }

        $count = $qb->getQuery()->getSingleScalarResult();

        return $count;
    }

    /**
     * @throws \ErrorException
     */
    private function executeCustomQuery($class, $query)
    {
        $repo = $this->entityManager->getRepository($class);
        if(!method_exists($repo, $query)){
            throw new \ErrorException($query.' is not a valid function.');
        }
        return $repo->{$query}();
    }
}
