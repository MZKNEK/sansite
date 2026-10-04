<?php
    $opts = [
        "http" => [
            "method" => "GET"
        ]
    ];
    $context = stream_context_create($opts);
    $url = 'http://api.sanakan.pl/api/Info/commands';
    $data = @json_decode(@file_get_contents($url, false, $context), true);

    $prefix = $data['prefix'] ?? '';
    $modules = $data['modules'] ?? [];

include 'sanakan.head.html';

?>
    <div class="row">
      <blockquote class="grey-text">
        Główny przedrostek bota:
        <tt class="purple-text text-lighten-2"><?=$prefix?></tt>
      </blockquote>
    </div>

<?php if(empty($modules)): ?>
    <div class="row">
      <blockquote class="grey-text">
        Nie udało się pobrać listy poleceń. Spróbuj ponownie później.
      </blockquote>
    </div>
<?php else: ?>
    <div class="toolbar">
      <div class="input-field">
        <i class="material-icons prefix">search</i>
        <input id="cmd-search" type="text" autocomplete="off" placeholder="Szukaj polecenia" aria-label="Szukaj polecenia" />
      </div>
      <div class="module-chips scaps">
        <?php foreach($modules AS $mi => $module): ?>
        <a href="#module-<?=$mi?>"><?=$module['name']?></a>
        <?php endforeach; ?>
      </div>
    </div>
<?php endif; ?>

<?php foreach($modules AS $mi => $module):?>
    <section class="module" id="module-<?=$mi?>">
        <h3 class="flow-text purple-text text-lighten-2"><?=$module['name']?></h3>
        <div class="divider grey darken-4"></div>
    <?php foreach($module['subModules'] AS $submodule):
        $smprefix = $submodule['prefix'];
        if($smprefix!='')
            $smprefix .=' ';
    ?>
      <div class="submodule">
    <?php if($smprefix!=''):?>
        <blockquote class="grey-text cmd-name">
            Przedrostek modułu:
            <tt class="purple-text text-lighten-2"><?=$smprefix?></tt>
            <div class="alias"><br />Aliasy:</div>
            <?php foreach($submodule['prefixAliases'] AS $pa): ?>
            <tt class="alias"><?=$pa?></tt>
            <?php endforeach; ?>
        </blockquote>
    <?php endif ?>
        <div class="table-wrap">
        <table class="highlight striped">
        <thead class="grey darken-4 grey-text text-lighten-1">
          <tr>
            <th>Nazwa</th>
            <th>Parametry</th>
            <th>Działanie</th>
            <th>Przykład</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($submodule['commands'] AS $command): ?>
                <tr>
                    <td>
                        <div class="cmd-name">
                            <strong><?=$command['name']?></strong>
                            <div class="alias">
                            <?php array_shift($command['aliases']); foreach($command['aliases'] AS $ca): ?>
                                <br /><span><?=$ca?></span>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if(!empty($command['attributes'])): ?>
                            <?php foreach($command['attributes'] AS $attr):?>
                                <?=$attr['description'] ?><br />
                            <?php endforeach; ?>
                        <?php endif ?>
                    </td>
                    <td><?=$command['description'] ?></td>
                    <td><?=$prefix.$smprefix.$command['name'].' '.$command['example'] ?></td>
                </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        </div>
      </div>
    <?php endforeach; ?>
    </section>
<?php endforeach; ?>

    <div class="no-results" id="no-results">Brak pasujących poleceń.</div>

<?php

include 'sanakan.foot.html';
