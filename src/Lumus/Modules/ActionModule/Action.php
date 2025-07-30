<?php

namespace Lumus\Modules\ActionModule;


class Action
{

    public function resultWithOpenLink(string $url): static
    {


        return $this;
    }

    public function resultWithBack(): static
    {


        return $this;
    }

    public function resultWithReload(): static
    {


        return $this;
    }

    public function resultWithRefresh(array $data): static
    {


        return $this;
    }


    public function resultWithError($msg): static
    {


        return $this;
    }


    public function resultWitError($msg): static
    {


        return $this;
    }
}
