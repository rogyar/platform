<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Action;

use Symfony\Component\PropertyAccess\PropertyPath;

use Oro\Bundle\WorkflowBundle\Model\Action\CreateEntity;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub;
use Oro\Bundle\WorkflowBundle\Model\ContextAccessor;

class CreateEntityTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CreateEntity
     */
    protected $action;

    /**
     * @var ContextAccessor
     */
    protected $contextAccessor;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $registry;

    protected function setUp()
    {
        $this->contextAccessor = new ContextAccessor();

        $this->registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')
            ->disableOriginalConstructor()
            ->getMock();

        $this->action = new CreateEntity($this->contextAccessor, $this->registry);
    }

    protected function tearDown()
    {
        unset($this->contextAccessor);
        unset($this->registry);
        unset($this->action);
    }

    /**
     * @param array $options
     * @dataProvider executeDataProvider
     */
    public function testExecute(array $options)
    {
        $em = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();
        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf($options[CreateEntity::OPTION_KEY_CLASS]));

        if (!empty($options[CreateEntity::OPTION_KEY_FLUSH])) {
            $em->expects($this->once())
                ->method('flush')
                ->with($this->isInstanceOf($options[CreateEntity::OPTION_KEY_CLASS]));
        } else {
            $em->expects($this->never())->method('flush');
        }

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($options[CreateEntity::OPTION_KEY_CLASS])
            ->will($this->returnValue($em));

        $context = new ItemStub(array());
        $attributeName = (string)$options[CreateEntity::OPTION_KEY_ATTRIBUTE];
        $this->action->initialize($options);
        $this->action->execute($context);
        $this->assertNotNull($context->$attributeName);
        $this->assertInstanceOf($options[CreateEntity::OPTION_KEY_CLASS], $context->$attributeName);

        /** @var ItemStub $entity */
        $entity = $context->$attributeName;
        $expectedData = !empty($options[CreateEntity::OPTION_KEY_DATA]) ?
            $options[CreateEntity::OPTION_KEY_DATA] :
            array();
        $this->assertInstanceOf($options[CreateEntity::OPTION_KEY_CLASS], $entity);
        $this->assertEquals($expectedData, $entity->getData());
    }

    /**
     * @return array
     */
    public function executeDataProvider()
    {
        return array(
            'without data' => array(
                'options' => array(
                    CreateEntity::OPTION_KEY_CLASS     => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                    CreateEntity::OPTION_KEY_ATTRIBUTE => new PropertyPath('test_attribute'),
                )
            ),
            'with data' => array(
                'options' => array(
                    CreateEntity::OPTION_KEY_CLASS     => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                    CreateEntity::OPTION_KEY_ATTRIBUTE => new PropertyPath('test_attribute'),
                    CreateEntity::OPTION_KEY_DATA      => array('key1' => 'value1', 'key2' => 'value2'),
                )
            ),
            'without flush' => array(
                'options' => array(
                    CreateEntity::OPTION_KEY_CLASS     => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                    CreateEntity::OPTION_KEY_ATTRIBUTE => new PropertyPath('test_attribute'),
                    CreateEntity::OPTION_KEY_DATA      => array('key1' => 'value1', 'key2' => 'value2'),
                    CreateEntity::OPTION_KEY_FLUSH     => false
                )
            ),
        );
    }

    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\NotManageableEntityException
     * @expectedExceptionMessage Entity class "stdClass" is not manageable.
     */
    public function testExecuteEntityNotManageable()
    {
        $options = array(
            CreateEntity::OPTION_KEY_CLASS     => 'stdClass',
            CreateEntity::OPTION_KEY_ATTRIBUTE => $this->getPropertyPath()
        );
        $context = array();
        $this->action->initialize($options);
        $this->action->execute($context);
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\ActionException
     * @expectedExceptionMessage Can't create entity stdClass. Test exception.
     */
    public function testExecuteCantCreateEntity()
    {
        $em = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();
        $em->expects($this->once())
            ->method('persist')
            ->will(
                $this->returnCallback(
                    function () {
                        throw new \Exception('Test exception.');
                    }
                )
            );

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->will($this->returnValue($em));

        $options = array(
            CreateEntity::OPTION_KEY_CLASS     => 'stdClass',
            CreateEntity::OPTION_KEY_ATTRIBUTE => $this->getPropertyPath()
        );
        $context = array();
        $this->action->initialize($options);
        $this->action->execute($context);
    }

    protected function getPropertyPath()
    {
        return $this->getMockBuilder('Symfony\Component\PropertyAccess\PropertyPath')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
