<!DOCTYPE html>
<html>
<head>
	<title>Gerador de CGNAT para RouterOS com netmap</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
	<script language="javascript">
	function dis_able()
	{
		if(document.formulario.int.value == 'null')
			document.formulario.nome.disabled=1;
		
		else
			document.formulario.nome.disabled=0;
	}
	</script>
</head>
<body>
	<?php 
		if (empty($_POST['c'])) { 
	?>

	<div style="padding: 30px;">
		<form method="POST" name="formulario">
			<h3>Gerador de CGNAT para RouterOS com netmap</h3>
			<div class="form-row">
				<div class="form-group col-md-6">
					<label for="c"><b>IP inicial inválido</b></label>
					<input type="text" class="form-control" id="c" name="c" placeholder="ex: 100.64.0.0">
				</div>
				<div class="form-group col-md-6">
					<label for="t"><b>1 público para quantos inválidos?</b></label>
					<select class="custom-select" name="t" id="t">
					<option value="2">2 (~32000 portas por IP Público)</option>
					<option value="4">4 (~16000 portas por IP Público)</option>
					<option value="8">8 (~8000 portas por IP Público)</option>
					<option value="16">16 (~4000 portas por IP Público)</option>
					<option value="32">32 (~2000 portas por IP Público)</option>
					<option value="64" selected="">64 (~1000 portas por IP Público)</option>
					<option value="128">128 (~500 portas por IP Público) Não recomendado</option>
					</select>
				</div>	
			</div>
			<div class="form-row">
				<div class="form-group col-md-6">
					<label for="s"><b>Bloco Público</b></label>
					<input type="text" class="form-control" id="s" name="s" placeholder="ex: 200.200.200.0/26">			
				</div>
				<div class="form-group col-md-6">
					<label for="intpub"><b>Nome da interface para adicionar os IPs Públicos</b></label>
					<input type="text" class="form-control" id="intpub" name="intpub" placeholder="ex: sfp-sfpplus1">
					<small id="obs" class="form-text text-muted">O bloco público será quebrado em /32 e adicionar a uma interface, informe o nome da mesma.</small>
				</div>
			</div>
			<div class="form-row">
				<div class="form-group col-md-6">
					<label for="int"><b>Interface Uplink</b></label>
					<select class="custom-select" name="int" id="int" onChange="dis_able()">
						<option value="null">Não Informar</option>
						<option value="nome">Nome da Interface</option>
						<option value="list">Interface List</option>				
					</select>
					<small id="obs" class="form-text text-muted">Se optar por int. list não esqueça de adicionar sua uplink a ela.</small>
				</div>	
				<div class="form-group col-md-6">
					<label for="nome"><b>Nome da interface</b></label>
					<input type="text" class="form-control" id="nome" name="nome" placeholder="ex: sfp1" disabled="" >
					<small id="obs" class="form-text text-muted">Nome da sua interface uplink ou da interface-list.</small>
				</div>
			</div>		
			<div class="form-group">		    
				<label class="form-check-label" for="protocol"><b>Protocolo: </b></label>
				<div class="form-check-inline">
					<label class="form-check-label">
						<input type="radio" class="form-check-input" checked="" name="protocol" value="none">TCP/UDP <small>(Recomendado)</small>
					</label>
				</div>			
				<div class="form-check-inline">
					<label class="form-check-label">
						<input type="radio" class="form-check-input" name="protocol" value="tcpudp">Apenas TCP
					</label>
				</div>
				<small id="obs" class="form-text text-muted">Algumas pessoas já alegaram ter algum problema fazendo para UDP.</small>
			</div>
			<hr>
			<div class="form-group">
				<button type="submit" class="btn btn-primary">Gerar Scritp</button>
			</div>
		</form>	
	</div>
	<?php
		} 
		else {
			echo "<pre style=\"padding: 10px;\">";

			$get_ip_mask = explode("/",$_POST['s']);

			if(!filter_var($_POST['c'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			  echo "IP inicial inválido.". $_POST['c']; die;
			}

			if(!filter_var($get_ip_mask[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			  echo "Bloco público inválido.".$get_ip_mask[0]; die;
			}
			if($get_ip_mask[1] == '31') { 
				echo "Máscara não suportada."; die;
			}

			$ip_count = 1 << (32 - $get_ip_mask[1]);

			$start = ip2long($get_ip_mask[0]);
			for ($i = 0; $i < $ip_count; $i++) {
				$saida_ips[] = long2ip($start + $i);
			}

			echo "# Adicione os IPs/32 um a um <br />";
			echo "/ip address <br />";

			foreach($saida_ips as $o) {
				echo "add address=$o/32 comment=\"CGNAT\" interface=".$_POST['intpub']." <br />";
			}

			if ($_POST['int'] == "null") {
				$interface_saida = null;
				$interface_nome = null;
				$interface = null;
			}
			elseif ($_POST['int'] == "nome") {
				$interface_saida = "out-interface";
				$interface_nome = $_POST['nome'];
				$interface = " $interface_saida=\"$interface_nome\"";
			}
			else {
				echo "<br /># Cria interface list <br />";
				echo "/interface list add name=" .$_POST['nome']. "<br /><br />";

				echo "# Não esqueça de adicionar sua interface de Uplink ao grupo ".$_POST['nome']." <br />";
				echo "/interface list member add list=".$_POST['nome']." interface=\"<b>*****</b>\" <br /><br />";

				$interface_saida = "out-interface-list";
				$interface_nome = $_POST['nome'];
				$interface = " $interface_saida=\"$interface_nome\"";
			}
			echo "<br /># Cria regras de CGNAT <br />";
			echo "/ip firewall nat <br />";
			$subnet_rev = array(
			    '20'  => '4096',
			    '21'  => '2048',
			    '22'  => '1024',
			    '23'   => '512',
			    '24'   => '256',
			    '25'   => '128',
			    '26'    => '64',
			    '27'    => '32',
			    '28'    => '16',
			    '29'     => '8',
			    '30'     => '4',
			    '32'     => '1'
			);

			$CGNAT_IP = ip2long($_POST['c']);
			$CGNAT_START = $_POST['s'];
			$CGNAT_RULES = $_POST['t'];
			$saida_regras = array();
			$saida_jumps = array();
			$x = 1;

			$rules = explode('/', $CGNAT_START);
			$ports = ceil((65535-1024)/$CGNAT_RULES);
			$ports_start = 1025;
			$ports_end = $ports_start + $ports;

			$public = explode('.', $rules[0]);
			$CGNAT_IP_INICIAL = $CGNAT_IP;
			$checkip = $CGNAT_IP_INICIAL;

			for($i=0;$i<$CGNAT_RULES;++$i){
				
				$saida_regras[] = "add action=netmap chain=CGNAT-{$x}$interface protocol=tcp src-address=".long2ip($CGNAT_IP)."/{$rules[1]} to-addresses={$CGNAT_START} to-ports={$ports_start}-{$ports_end}";
				
				if ($_POST['protocol'] == 'none'){
					$saida_regras[] = "add action=netmap chain=CGNAT-{$x}$interface protocol=udp src-address=".long2ip($CGNAT_IP)."/{$rules[1]} to-addresses={$CGNAT_START} to-ports={$ports_start}-{$ports_end}";
				}

				$saida_regras[] = "add action=netmap chain=CGNAT-{$x}$interface src-address=".long2ip($CGNAT_IP)."/{$rules[1]} to-addresses={$CGNAT_START}";
				$CGNAT_IP += $subnet_rev[$rules[1]];

				if($i==$CGNAT_RULES-1 && $x == 1){
					$saida_jumps[] = "add chain=srcnat src-address=".long2ip($CGNAT_IP_INICIAL)."-".long2ip($CGNAT_IP-1)." action=jump jump-target=\"CGNAT-{$x}\"";
				}
				
				$check = $CGNAT_IP - $CGNAT_IP_INICIAL;
				if($check>255) {
					$saida_jumps[] = "add chain=srcnat src-address=".long2ip($CGNAT_IP_INICIAL)."-".long2ip($CGNAT_IP-1)." action=jump jump-target=\"CGNAT-{$x}\"";
					$CGNAT_IP_INICIAL = $CGNAT_IP;
					++$x;
				}
				
				$ports_start = $ports_end + 1;
				if($ports_start >= 65535) {
					$ports_start = 1025;
					$ports_end = $ports_start;
				}
				
				$ports_end += $ports;
				if($ports_end > 65535){
					$ports_end = 65535;
				}
			}

			foreach($saida_jumps as $o) {
			    echo "$o <br />";
			}
			foreach($saida_regras as $f) {
			    echo "$f <br />";
			}
			echo "<pre>";
		}
	?>
	<div align="center">
		<a href="https://blog.remontti.com.br/doar">Fazer uma Doação</a> | <a href="https://github.com/remontti/php-cgnat-netmap-routeros">Código Fonte</a>
		<p><small>Desenvolvido por Rudimar Remontti</small></p>
		<div id="fb-root"></div>
		<script async defer crossorigin="anonymous" src="https://connect.facebook.net/pt_BR/sdk.js#xfbml=1&version=v6.0&appId=630735496970056&autoLogAppEvents=1"></script>
		<div class="fb-like" data-href="https://www.facebook.com/root.remontti/" data-width="" data-layout="button" data-action="like" data-size="large" data-share="true"></div>
	</div>
</body>
</html>