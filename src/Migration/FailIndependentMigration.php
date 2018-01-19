<?php

namespace Okvpn\Component\Migration\Migration;

/**
 * This is a marker interface that can be used to mark migrations which
 * should be executed event if previous migrations failed.
 */
interface FailIndependentMigration
{
}
