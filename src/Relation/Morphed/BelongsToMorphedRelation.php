<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Relation\Morphed;


use Spiral\ORM\Command\CommandInterface;
use Spiral\ORM\Command\ContextCarrierInterface;
use Spiral\ORM\Node;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\PromiseInterface;
use Spiral\ORM\Relation;
use Spiral\ORM\Relation\BelongsToRelation;
use Spiral\ORM\Util\Promise;

class BelongsToMorphedRelation extends BelongsToRelation
{
    /** @var mixed|null */
    private $morphKey;

    /**
     * @param ORMInterface $orm
     * @param string       $target
     * @param string       $relation
     * @param array        $schema
     */
    public function __construct(ORMInterface $orm, string $relation, string $target, array $schema)
    {
        parent::__construct($orm, $relation, $target, $schema);
        $this->morphKey = $schema[Relation::MORPH_KEY] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function initPromise(Node $point): array
    {
        if (empty($innerKey = $this->fetchKey($point, $this->innerKey))) {
            return [null, null];
        }

        // parent class (todo: i don't need it!!!!!!!! use aliases directly)
        // todo: yeeeep, need aliases directly

        $parentClass = $this->orm->getSchema()->getClass($this->fetchKey($point, $this->morphKey));

        $scope = [$this->outerKey => $innerKey];

        if (!empty($e = $this->orm->get($parentClass, $scope, false))) {
            return [$e, $e];
        }


        //        // todo: i don't like carrying alias in a context (!!!!)
        //        // this is not right (!!)
        $pr = new Promise(
            $this->fetchKey($point, $this->morphKey),
            [
                $this->outerKey => $innerKey,
                $this->morphKey => $this->fetchKey($point, $this->morphKey)
            ]
            , function ($context) use ($innerKey) {

            $parentClass = $this->orm->getSchema()->getClass($context[$this->morphKey]);
            return $this->orm->get($parentClass, [$this->outerKey => $innerKey], true);
        });

        return [$pr, $pr];
    }

    /**
     * @inheritdoc
     */
    public function queue(
        ContextCarrierInterface $parentStore,
        $parentEntity,
        Node $parentNode,
        $related,
        $original
    ): CommandInterface {
        $store = parent::queue($parentStore, $parentEntity, $parentNode, $related, $original);

        // todo: use forward as well

        if (is_null($related)) {
            if ($this->fetchKey($parentNode, $this->morphKey) !== null) {
                $parentStore->register($this->morphKey, null, true);
                $parentNode->setData([$this->morphKey => null]);
            }
        } else {
            $relState = $this->getNode($related);
            if ($this->fetchKey($parentNode, $this->morphKey) != $relState->getRole()) {
                $parentStore->register($this->morphKey, $relState->getRole(), true);
                $parentNode->setData([$this->morphKey => $relState->getRole()]);
            }
        }

        return $store;
    }

    protected function getNode($entity, int $claim = 0): ?Node
    {
        if ($entity instanceof PromiseInterface) {
            $scope = $entity->__scope();

            return new Node(
                Node::PROMISED,
                [$this->outerKey => $scope[$this->outerKey]],
                $scope[$this->morphKey]
            );
        }

        return parent::getNode($entity, $claim);
    }
}