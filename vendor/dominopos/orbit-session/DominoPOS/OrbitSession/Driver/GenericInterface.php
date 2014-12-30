<?php namespace DominoPOS\OrbitSession\Driver;
/**
 * Generic interface which all session driver should implements.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
interface GenericInterface
{
    /**
     * Start a session
     *
     * @param DominoPOS\OrbitSession\SessionData
     */
    public function start($sessionData);

    /**
     * Update a session
     *
     * @param DominoPOS\OrbitSession\SessionData
     */
    public function update($sessionData);

    /**
     * Destroy a session
     */
    public function destroy($sessionId);

    /**
     * Get a session
     */
    public function get($sessionId);

    /**
     * Delete expire session
     */
    public function deleteExpires();
}
