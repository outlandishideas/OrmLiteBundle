ORM Lite Bundle
===============

[Doctrine](http://www.doctrine-project.org/projects/orm.html) is great for managing relatively small numbers of objects.
But when you need to handle large datasets (e.g. > 10,000 objects), the increased memory usage and slow queries start to
become a problem.

This bundle lets you continue to use some of Doctrine's features with minimal overhead.

### Stuff that works

- Mappings and schema management
- Migrations and fixtures
- Queries with simple criteria such as `array('userId' => 1)`
- Bulk INSERT, UPDATE and DELETE
- Entities with public properties

### Stuff that doesn't work

- Entities with private or protected properties
- Associations and lazy loading
- Tracking object state and flushing changes
- Compound primary keys
- Custom repository classes
- Probably a lot more

Installation
------------

1. Require in `composer.json`: `"outlandish/orm-lite-bundle": "dev-master"`
2. Activate in `AppKernel.php`: `new \Outlandish\OrmLiteBundle\OrmLiteBundle(),`
3. Import into `config.yml`: `resource: @OrmLiteBundle/Resources/config/services.yml`

Usage Example
-------------

Point.php

    /** @Entity */
    class Point {
        /** @Id @Column(type="integer") @GeneratedValue */
        public $id;
        /** @Column(type="integer") */
        public $x;
        /** @Column(type="integer") */
        public $y;
    }

PointController.php

    class PointController extends ContainerAware {
        public function exampleAction() {
            //get entity manager from service container
            $ormLite = $this->container->get('outlandish.ormlite');

            //get array of objects of class 'Point' where x=0
            $points = $ormLite->findBy('Point', array('x' => 0));

            //modify objects
            foreach ($points as $point) {
                $point->x = 1;
            }

            //persist them
            $ormLite->update($points);

            //create some new objects
            $newPoints = array();
            for ($i=0; $i < 10000; $i++) {
                $point = new Point();
                $point->x = random(-50, 50);
                $point->y = random(-10, 10);
                $newPoints[] = $point;
            }

            //persist them
            $ormLite->insert($newPoints);
        }
    }

Performance
-----------

Not formally benchmarked yet.