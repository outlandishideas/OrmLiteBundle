<?php


namespace Outlandish\OrmLiteBundle;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;

/**
 * Perform a limited set of ORM actions on objects using Doctrine mapping configuration
 *
 * @package Outlandish\OrmLiteBundle
 */
class OrmLite {

	/**
	 * @var Connection
	 */
	protected $conn;

	/**
	 * @var EntityManager
	 */
	protected $em;

	/**
	 * Maximum number of INSERTs to perform per query
	 * @var int
	 */
	protected $maxInsertSize = 100;

	/**
	 * Construct OrmLite class using a Doctrine entity manager instance
	 *
	 * @param EntityManager $em
	 */
	public function __construct(EntityManager $em) {
		$this->conn = $em->getConnection();
		$this->em = $em;
	}

	/**
	 * Insert entities into database using extended insert syntax
	 *
	 * @param array $entities
	 */
	public function insert(array $entities) {

		$dataSize = count($entities);
		if ($dataSize) {

			$entities = array_values($entities);
			$metadata = $this->em->getClassMetadata(get_class($entities[0]));
			$tableName = $metadata->getTableName();
			$columnNames = $metadata->getColumnNames();

			//create placeholders
			$columnNamesString = implode(',', $columnNames);
			$placeholders = '(' . implode(',', array_fill(0, count($columnNames), '?')) . ')';

			$this->conn->beginTransaction();

			//temporarily disable query logging
			$logger = $this->conn->getConfiguration()->getSQLLogger();
			$this->conn->getConfiguration()->setSQLLogger();

			//chunk up inserts
			for ($cursor = 0; $cursor < $dataSize; $cursor += $this->maxInsertSize) {
				$sliceData = array_slice($entities, $cursor, $this->maxInsertSize);

				$allPlaceholders = implode(',', array_fill(0, count($sliceData), $placeholders));
				$query = "INSERT INTO $tableName ($columnNamesString) VALUES $allPlaceholders";

				//make single array of all values
				$values = array();
				foreach ($sliceData as $record) {
					$row = get_object_vars($record);
					foreach ($row as $col) {
						$values[] = $col;
					}
				}

				//insert the data
				$this->conn->executeQuery($query, $values);
			}

			//re-enable logging
			$this->conn->getConfiguration()->setSQLLogger($logger);

			$this->conn->commit();
		}
	}

	/**
	 * Delete entities
	 *
	 * @param array $entities
	 */
	public function delete(array $entities) {
		if (count($entities)) {

			$entities = array_values($entities);
			$metadata = $this->em->getClassMetadata(get_class($entities[0]));
			$tableName = $metadata->getTableName();
			$idCol = $metadata->getSingleIdentifierColumnName();
			$idField = $metadata->getSingleIdentifierFieldName();

			$ids = array();
			foreach ($entities as $entity) {
				$ids[] = $this->conn->quote($entity->$idField);
			}
			$this->conn->executeQuery("DELETE FROM $tableName WHERE $idCol IN (" . implode(',', $ids) . ")");
		}
	}

	/**
	 * Update entities already present in database
	 *
	 * @param array $entities
	 */
	public function update(array $entities) {
		if (count($entities)) {

			$entities = array_values($entities);
			$metadata = $this->em->getClassMetadata(get_class($entities[0]));
			$tableName = $metadata->getTableName();
			$columnNames = $metadata->getColumnNames();
			$idCol = $metadata->getSingleIdentifierColumnName();
			$idField = $metadata->getSingleIdentifierFieldName();

			//create update clause
			$updaters = array();
			foreach ($columnNames as $column) {
				$updaters[] = "$column = ?";
			}
			$updateClause = implode(',', $updaters);

			$this->conn->beginTransaction();

			//temporarily disable query logging
			$logger = $this->conn->getConfiguration()->getSQLLogger();
			$this->conn->getConfiguration()->setSQLLogger();

			$query = "UPDATE $tableName SET $updateClause WHERE $idCol = ?";
			$statement = $this->conn->prepare($query);

			foreach ($entities as $entity) {
				$values = array_values(get_object_vars($entity));
				$values[] = $entity->$idField;
				$statement->execute($values);
			}

			//re-enable logging
			$this->conn->getConfiguration()->setSQLLogger($logger);

			$this->conn->commit();
		}

	}

	/**
	 * Query database for entities using simple criteria, e.g. array('userId' => 12)
	 *
	 * @param string $className Fully qualified class name of entity
	 * @param array $criteria
	 * @return array
	 */
	public function findBy($className, array $criteria) {
		$metadata = $this->em->getClassMetadata($className);
		$tableName = $metadata->getTableName();
		$sql = "SELECT * FROM $tableName";

		if (count($criteria)) {
			$clauses = array();
			foreach ($criteria as $fieldName => $value) {
				$clauses[] = $metadata->fieldMappings[$fieldName]['columnName'] . ' = :'.$fieldName;
			}
			$sql .= ' WHERE '.implode(' AND ', $clauses);
		}

		$stmt = $this->conn->executeQuery($sql, $criteria);
		return $stmt->fetchAll(\PDO::FETCH_CLASS, $className);
	}

	/**
	 * Fetch all entities of given class
	 *
	 * @param string $className Fully qualified class name of entity
	 * @return array
	 */
	public function findAll($className) {
		return $this->findBy($className, array());
	}
}