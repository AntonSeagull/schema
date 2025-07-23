<?php

namespace Lumus\Modules\ActionModule;


class Action
{

    public function resultWithOpenLink(string $url): self
    {


        return $this;
    }

    public function resultWithBack(): self
    {


        return $this;
    }

    public function resultWithReload(): self
    {


        return $this;
    }

    public function resultWithRefresh(array $data): self
    {


        return $this;
    }


    public function resultWithError($msg): self
    {


        return $this;
    }


    public function resultWitError($msg): self
    {


        return $this;
    }
}
