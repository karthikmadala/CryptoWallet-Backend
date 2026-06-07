import json
from graphify.build import build_from_json
from graphify.cluster import score_all
from graphify.analyze import god_nodes, surprising_connections, suggest_questions
from graphify.report import generate
from pathlib import Path

extraction = json.loads(Path(".graphify_extract.json").read_text(encoding="utf-8"))
detection  = json.loads(Path(".graphify_detect.json").read_text(encoding="utf-8"))
analysis   = json.loads(Path(".graphify_analysis.json").read_text(encoding="utf-8"))

G = build_from_json(extraction)
communities = {int(k): v for k, v in analysis["communities"].items()}
cohesion = {int(k): v for k, v in analysis["cohesion"].items()}
tokens = {"input": extraction.get("input_tokens", 0), "output": extraction.get("output_tokens", 0)}

named = {
    0: "HTTP Layer (Controllers & Handlers)",
    1: "Core Services & api_response Contract",
    2: "ICO Purchase Lifecycle",
    3: "Audit, Logs & Portfolio/Tx Controllers",
    4: "ICO Signature & ABI Encoding",
    5: "Service Container Bindings & File Upload",
    6: "Admin/Chain Controllers & Portfolio Service",
    7: "RBAC & User Resources",
    8: "Transaction Service & Gas DTOs",
    9: "Transaction Monitor & Repository",
    10: "Auth & Explorer Services",
    11: "Staking Service & Hex Utilities",
    12: "BlockchainNodeService (501 Stub)",
    13: "EVM Tx Signing & RLP Encoding",
    14: "Wallet Sync Jobs & Repository",
    15: "Service Providers & EvmRpcService",
    16: "App Branding & Settings",
    17: "Gas Estimation Service",
    18: "Crypto Balance Providers (Alchemy/BlockCypher/Info)",
    19: "Blockchain Exceptions & ChainType Enum",
    20: "Auth Middleware & API Logging",
    21: "ICO Sale Controller",
    22: "Wallet Model & Relationships",
    23: "Wallet Service",
    24: "OTP Service & EmailOtp",
    25: "Wallet Repository Interface",
    26: "Google OAuth & Social Accounts",
    27: "Wallet Generation Service",
    28: "Transaction Policy (BOLA)",
    29: "BlockchainInfo & ERC-20 Balance",
}
labels = {cid: named.get(cid, f"Community {cid}") for cid in communities}

questions = suggest_questions(G, communities, labels)
report = generate(G, communities, cohesion, labels, analysis["gods"], analysis["surprises"], detection, tokens, ".", suggested_questions=questions)
Path("graphify-out/GRAPH_REPORT.md").write_text(report, encoding="utf-8")
Path(".graphify_labels.json").write_text(json.dumps({str(k): v for k, v in labels.items()}), encoding="utf-8")
print(f"Report updated, {len(questions)} questions")
